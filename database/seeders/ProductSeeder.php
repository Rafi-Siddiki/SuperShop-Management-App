<?php

namespace Database\Seeders;

use App\Support\Barcode;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Loads the catalogue from database/seeders/data/products.json (built from
 * DummyJSON product data and Open Library book covers) and downloads each
 * product photo into storage/app/public/products. Photos already on disk are
 * reused, so re-seeding works offline after the first run; a product whose
 * photo cannot be fetched simply shows the placeholder image.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = json_decode(file_get_contents(database_path('seeders/data/products.json')), true);

        $paths = $this->downloadImages($catalog);

        $now = now();
        $rows = [];
        $usedBarcodes = [];

        foreach ($catalog as $i => $item) {
            // Most of the range has been on sale for over a year; some items are recent additions.
            $roll = mt_rand(1, 100);
            $listed = match (true) {
                $roll <= 78 => $now->copy()->subDays(mt_rand(380, 540)),
                $roll <= 92 => $now->copy()->subDays(mt_rand(30, 300)),
                default => $now->copy()->subDays(mt_rand(2, 20)),
            };

            $price = (float) $item['price'];
            $rows[] = [
                'name' => $item['name'],
                'barcode' => $this->barcodeFor($item, $usedBarcodes),
                'description' => $item['description'],
                'date' => $listed->toDateString(),
                'image' => $paths[$i] ?? null,
                'category' => $item['category'],
                'price' => $price,
                // Typical retail gross margins: groceries thin, fashion and beauty fat.
                'cost_price' => round($price * match ($item['category']) {
                    'Food' => mt_rand(76, 86),
                    'Electronics' => mt_rand(78, 90),
                    'Books' => mt_rand(65, 75),
                    'Clothing', 'Beauty & Health', 'Others' => mt_rand(50, 65),
                    default => mt_rand(60, 75),
                } / 100),
                'reorder_level' => match (true) {
                    $item['category'] === 'Food' => mt_rand(15, 25),
                    $price > 50000 => 3,
                    $price > 10000 => 5,
                    default => mt_rand(8, 12),
                },
                'is_active' => true,
                'created_at' => $listed,
                'updated_at' => $listed,
            ];
        }

        DB::table('products')->insert($rows);

        // SKUs, and internal in-store barcodes for products without a manufacturer code.
        foreach (DB::table('products')->get(['id', 'barcode']) as $product) {
            DB::table('products')->where('id', $product->id)->update([
                'sku' => 'SKU-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                'barcode' => $product->barcode ?? Barcode::internalFor($product->id),
            ]);
        }

        // Two seasonal lines are paused (inactive) to show that state.
        DB::table('products')->whereIn('name', ['Dress Pea', 'Football'])->update(['is_active' => false]);

        // Stock rows start empty: OrderSeeder replays a year of deliveries and sales into the ledger.
        DB::table('stocks')->insert(DB::table('products')->pluck('id')->map(fn ($id) => [
            'product_id' => $id, 'quantity' => 0, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    /**
     * Books use their ISBN (an ISBN-13 is a valid EAN-13). Most packaged goods
     * get a realistic EAN-13 with Bangladesh's GS1 prefix 941; loose and
     * unbranded items (null here) get an internal in-store code afterwards.
     */
    private function barcodeFor(array $item, array &$used): ?string
    {
        if (preg_match('/isbn\/(\d{13})/', $item['image_url'], $m)) {
            return $used[$m[1]] = $m[1];
        }

        if (in_array($item['category'], ['Food', 'Clothing'], true) && mt_rand(1, 100) <= 45) {
            return null;
        }

        do {
            $payload = '941' . str_pad((string) mt_rand(0, 999_999_999), 9, '0', STR_PAD_LEFT);
            $code = $payload . Barcode::checkDigit($payload);
        } while (isset($used[$code]));

        return $used[$code] = $code;
    }

    /** @return array<int, string> catalogue index => stored image path */
    private function downloadImages(array $catalog): array
    {
        $disk = Storage::disk('public');
        $paths = [];
        $missing = [];

        foreach ($catalog as $i => $item) {
            $ext = Str::contains($item['image_url'], '.webp') ? 'webp' : 'jpg';
            $path = 'products/' . Str::slug(Str::limit($item['name'], 60, '')) . '.' . $ext;

            if ($disk->exists($path) && $disk->size($path) > 1000) {
                $paths[$i] = $path;
            } else {
                $missing[$i] = $path;
            }
        }

        if ($missing === []) {
            $this->command?->info('All ' . count($paths) . ' product images already downloaded.');

            return $paths;
        }

        $this->command?->info('Downloading ' . count($missing) . ' product images…');
        $failed = 0;

        // Many Windows PHP builds ship without a CA bundle; use Composer's so HTTPS works everywhere.
        $verify = class_exists(\Composer\CaBundle\CaBundle::class)
            ? \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()
            : true;

        foreach (array_chunk($missing, 10, true) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => collect($batch)->map(
                fn ($path, $i) => $pool->as((string) $i)
                    ->withOptions(['verify' => $verify])
                    ->withHeaders(['User-Agent' => 'SupershopSeeder/1.0'])
                    ->timeout(30)
                    ->get($catalog[$i]['image_url'])
            )->all());

            foreach ($batch as $i => $path) {
                $response = $responses[(string) $i] ?? null;

                if ($response instanceof \Illuminate\Http\Client\Response
                    && $response->successful()
                    && str_starts_with((string) $response->header('Content-Type'), 'image/')
                    && strlen($response->body()) > 1000) {
                    $disk->put($path, $response->body());
                    $paths[$i] = $path;
                } else {
                    $failed++;
                }
            }
        }

        if ($failed) {
            $this->command?->warn("$failed images could not be downloaded; those products use the placeholder.");
        }

        return $paths;
    }
}
