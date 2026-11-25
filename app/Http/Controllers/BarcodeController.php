<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Barcode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Barcode images, scanner lookups and printable label sheets.
 */
class BarcodeController extends Controller
{
    /** SVG barcode for a product (used on screen; cached by the browser). */
    public function image(Product $product)
    {
        abort_unless($product->barcode, 404);

        return response(Barcode::svg($product->barcode, 2, 60), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Scan / lookup page for staff: USB scanner, camera or typed code. */
    public function scan()
    {
        return view('barcode.scan');
    }

    /**
     * Find products for scanners and the till.
     *  ?code=…  exact barcode or SKU (what a scanner sends) → one product
     *  ?q=…     name / SKU / barcode search → up to 8 products
     */
    public function lookup(Request $request): JsonResponse
    {
        if ($request->filled('code')) {
            $raw = (string) $request->query('code');
            $product = Product::findByCode($raw, withTrashed: true);
            $code = Barcode::normalize($raw);

            if (! $product) {
                $hint = ctype_digit($code) && in_array(strlen($code), [8, 12, 13, 14], true) && ! Barcode::isValidGtin($code)
                    ? ' The check digit is wrong — the code was probably misread; scan again.'
                    : '';

                return response()->json(['message' => "No product with barcode or SKU “{$code}”.{$hint}"], 404);
            }

            return response()->json(['product' => $this->present($product)]);
        }

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::with('stock')->active()->search($term)->orderBy('name')->limit(8)->get();

        return response()->json(['products' => $products->map(fn ($p) => $this->present($p))->values()]);
    }

    public function labels(Request $request)
    {
        $selected = Product::with('stock')
            ->whereIn('id', array_map('intval', (array) $request->query('ids', [])))
            ->get();

        return view('barcode.labels', ['selected' => $selected]);
    }

    /** A4 sheet of 3 × 8 labels (63.5 × 33.9 mm, the common "24 per sheet" size). */
    public function printLabels(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.copies' => 'required|integer|min:0|max:500',
            'show_price' => 'boolean',
            'skip' => 'nullable|integer|min:0|max:23',
        ]);

        $products = Product::withTrashed()->whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');

        $labels = [];
        foreach ($data['items'] as $item) {
            $product = $products[$item['product_id']];
            for ($i = 0; $i < $item['copies']; $i++) {
                $labels[] = $product;
            }
        }

        if ($labels === []) {
            return back()->withInput()->with('error', 'Choose at least one label to print.');
        }
        if (count($labels) > 2400) {
            return back()->withInput()->with('error', 'That is more than 100 sheets; please print in smaller batches.');
        }

        // Render each distinct barcode image once.
        $images = collect($labels)->unique('id')->mapWithKeys(fn ($p) => [$p->id => Barcode::pngDataUri($p->barcode, 2, 50)]);

        // Skip already-used positions on a partly used sheet.
        $cells = array_merge(array_fill(0, (int) ($data['skip'] ?? 0), null), $labels);

        return Pdf::loadView('barcode.labels-pdf', [
            'pages' => array_chunk($cells, 24),
            'images' => $images,
            'showPrice' => $request->boolean('show_price', true),
        ])->setPaper('a4')->download('barcode_labels_' . now()->format('Y_m_d_His') . '.pdf');
    }

    private function present(Product $product): array
    {
        $product->loadMissing('stock');

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'category' => $product->category,
            'price' => (float) $product->price,
            'cost_price' => auth()->user()?->isAdmin() && $product->cost_price !== null ? (float) $product->cost_price : null,
            'stock' => $product->stock_quantity,
            'reorder_level' => $product->reorder_threshold,
            'sellable' => $product->is_sellable,
            'status' => $product->trashed() ? 'deleted' : ($product->is_active ? 'active' : 'inactive'),
            'image_url' => $product->image_url,
            'barcode_url' => $product->barcode ? route('barcodes.image', $product) : null,
            'edit_url' => auth()->user()?->isAdmin() && ! $product->trashed() ? route('products.edit', $product) : null,
            'history_url' => route('inventory.movements', ['product' => $product->id]),
        ];
    }
}
