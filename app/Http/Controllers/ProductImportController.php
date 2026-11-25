<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\InventoryService;
use App\Support\Barcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk product import/export as CSV (opens in Excel / Google Sheets).
 *
 * Import is all-or-nothing: every row is validated first and nothing is
 * saved if any row has a problem. Rows match existing products by SKU, then
 * by barcode; unmatched rows create new products. A `stock` value is recorded
 * as a stock count in the inventory ledger.
 */
class ProductImportController extends Controller
{
    public const COLUMNS = ['sku', 'barcode', 'name', 'category', 'price', 'cost_price', 'reorder_level', 'active', 'stock', 'description'];
    private const MAX_ROWS = 5000;

    public function show()
    {
        return view('products.import', ['columns' => self::COLUMNS, 'categories' => Product::CATEGORIES]);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS);

            Product::with('stock')->orderBy('id')->chunk(500, function ($products) use ($out) {
                foreach ($products as $p) {
                    fputcsv($out, [$p->sku, $p->barcode, $p->name, $p->category, $p->price, $p->cost_price,
                        $p->reorder_level, $p->is_active ? 'yes' : 'no', $p->stock_quantity, $p->description]);
                }
            });

            fclose($out);
        }, 'products_' . now()->format('Y_m_d_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS);
            fputcsv($out, ['', '8901030865275', 'Example Soap 100g', 'Beauty & Health', '75', '58', '24', 'yes', '120', 'Leave sku/barcode empty to generate them']);
            fclose($out);
        }, 'product_import_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, InventoryService $inventory)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        [$rows, $errors] = $this->parse($request->file('file')->getRealPath());

        if (! $errors) {
            $errors = $this->validateRows($rows);
        }

        if ($errors) {
            return back()->with('import_errors', array_slice($errors, 0, 50))
                ->with('error', count($errors) . ' problem(s) found — nothing was imported. Fix the file and upload it again.');
        }

        $result = [];

        // Retried on deadlock, so the counters are reset inside the transaction.
        DB::transaction(function () use ($rows, $inventory, $request, &$result) {
            $result = ['created' => 0, 'updated' => 0, 'stock' => 0];

            foreach ($rows as $row) {
                $attributes = array_filter([
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'price' => $row['price'],
                    'cost_price' => $row['cost_price'],
                    'reorder_level' => $row['reorder_level'],
                    'is_active' => $row['active'],
                    'description' => $row['description'],
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'],
                ], fn ($v) => $v !== null);

                if ($product = $row['match']) {
                    $product->update($attributes);
                    $result['updated']++;
                } else {
                    $product = Product::create($attributes + [
                        'description' => $row['description'] ?? $row['name'],
                        'date' => now()->toDateString(),
                        'reorder_level' => $row['reorder_level'] ?? (int) setting('default_reorder_level'),
                    ]);
                    $result['created']++;
                }

                if ($row['stock'] !== null) {
                    $current = $product->stock()->value('quantity');
                    if ($current === null && ! $row['match']) {
                        $inventory->opening($product, $row['stock'], $request->user());
                        $result['stock']++;
                    } elseif ((int) $current !== $row['stock']) {
                        $inventory->count($product, $row['stock'], 'CSV import', $request->user());
                        $result['stock']++;
                    }
                }
            }
        }, 3);

        return redirect()->route('products.index')->with('success',
            "Import complete: {$result['created']} created, {$result['updated']} updated, {$result['stock']} stock levels changed.");
    }

    /** @return array{0: array<int, array>, 1: array<int, string>} */
    private function parse(string $path): array
    {
        // Strip the UTF-8 byte-order mark (Excel, Google Sheets) before parsing,
        // otherwise a quoted first header keeps its quotes.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle);

        if (! $header || $header === [null]) {
            fclose($handle);

            return [[], ['The file is empty.']];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $unknown = array_diff($header, self::COLUMNS);
        $missing = array_diff(['name', 'category', 'price'], $header);
        if ($unknown || $missing) {
            fclose($handle);

            return [[], array_filter([
                $unknown ? 'Unknown column(s): ' . implode(', ', $unknown) . '. Allowed: ' . implode(', ', self::COLUMNS) . '.' : null,
                $missing ? 'Missing required column(s): ' . implode(', ', $missing) . '.' : null,
            ])];
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return [[], ['The file has more than ' . self::MAX_ROWS . ' rows; split it into smaller files.']];
            }
            $values = array_pad(array_map(fn ($c) => trim((string) $c), $cells), count($header), '');
            $rows[$line] = array_combine($header, array_slice($values, 0, count($header)));
        }
        fclose($handle);

        return $rows ? [$rows, []] : [[], ['The file has no product rows.']];
    }

    /** Validate and normalise rows in place; returns error messages. */
    private function validateRows(array &$rows): array
    {
        $errors = [];
        $seenSku = [];
        $seenBarcode = [];

        foreach ($rows as $line => &$row) {
            $problems = [];
            $value = fn ($key) => isset($row[$key]) && $row[$key] !== '' ? $row[$key] : null;

            $sku = $value('sku') !== null ? strtoupper($value('sku')) : null;
            $barcode = $value('barcode') !== null ? Barcode::normalize($value('barcode')) : null;

            if ($sku !== null && ! preg_match('/^[A-Z0-9][A-Z0-9\-_.\/]{0,63}$/', $sku)) {
                $problems[] = "SKU “{$sku}” may only contain letters, digits and - _ . /";
            }
            if ($barcode !== null && ! Barcode::isValidGtin($barcode)) {
                $problems[] = "barcode “{$barcode}” is not a valid EAN/UPC (check digit)";
            }
            if ($sku !== null && isset($seenSku[$sku])) {
                $problems[] = "SKU {$sku} also appears on row {$seenSku[$sku]}";
            }
            if ($barcode !== null && isset($seenBarcode[$barcode])) {
                $problems[] = "barcode {$barcode} also appears on row {$seenBarcode[$barcode]}";
            }

            $match = $sku !== null ? Product::withTrashed()->where('sku', $sku)->first() : null;
            $match ??= $barcode !== null ? Product::withTrashed()->where('barcode', $barcode)->first() : null;

            if ($match?->trashed()) {
                $problems[] = "{$match->sku} belongs to a deleted product";
            }
            if ($barcode !== null && Product::withTrashed()->where('barcode', $barcode)->when($match, fn ($q) => $q->whereKeyNot($match->id))->exists()) {
                $problems[] = "barcode {$barcode} is already used by another product";
            }
            if ($sku !== null && Product::withTrashed()->where('sku', $sku)->when($match, fn ($q) => $q->whereKeyNot($match->id))->exists()) {
                $problems[] = "SKU {$sku} is already used by another product";
            }

            $name = $value('name');
            if ($name === null || mb_strlen($name) > 255) {
                $problems[] = 'name is required (max 255 characters)';
            }
            if (! in_array($value('category'), Product::CATEGORIES, true)) {
                $problems[] = 'category must be one of: ' . implode(', ', Product::CATEGORIES);
            }
            foreach (['price' => true, 'cost_price' => false] as $key => $required) {
                $v = $value($key);
                if (($required && $v === null) || ($v !== null && (! is_numeric($v) || $v < 0 || $v > 99999999))) {
                    $problems[] = "{$key} must be a number of 0 or more";
                }
            }
            foreach (['reorder_level', 'stock'] as $key) {
                $v = $value($key);
                if ($v !== null && (! ctype_digit($v) || (int) $v > 1000000)) {
                    $problems[] = "{$key} must be a whole number of 0 or more";
                }
            }
            $active = $value('active');
            if ($active !== null && ! in_array(strtolower($active), ['yes', 'no', '1', '0', 'true', 'false'], true)) {
                $problems[] = 'active must be yes or no';
            }

            if ($problems) {
                $errors[] = "Row {$line}: " . implode('; ', $problems) . '.';
                continue;
            }

            if ($sku !== null) {
                $seenSku[$sku] = $line;
            }
            if ($barcode !== null) {
                $seenBarcode[$barcode] = $line;
            }

            $row = [
                'match' => $match,
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $name,
                'category' => $value('category'),
                'price' => $value('price'),
                'cost_price' => $value('cost_price'),
                'reorder_level' => $value('reorder_level') !== null ? (int) $value('reorder_level') : null,
                'active' => $active !== null ? in_array(strtolower($active), ['yes', '1', 'true'], true) : null,
                'stock' => $value('stock') !== null ? (int) $value('stock') : null,
                'description' => $value('description'),
            ];
        }
        unset($row);

        return $errors;
    }
}
