<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock levels, deliveries, write-offs, stock counts and the movement ledger.
 * Receiving is open to all staff; write-offs and counts are admin-only (see routes).
 */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $filter = $request->query('filter');

        $products = Product::with('stock')
            ->search($search)
            ->inCategory($category)
            ->when($filter === 'reorder', fn ($q) => $q->needsReorder())
            ->when($filter === 'low', fn ($q) => $q->needsReorder()->whereHas('stock', fn ($s) => $s->where('quantity', '>', 0)))
            ->when($filter === 'out', fn ($q) => $q->whereHas('stock', fn ($s) => $s->where('quantity', 0)))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy(Stock::select('quantity')->whereColumn('stocks.product_id', 'products.id')->limit(1))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', [
            'products' => $products,
            'summary' => $this->inventory->summary(),
            'search' => $search,
            'category' => $category,
            'filter' => $filter,
            'categories' => Product::CATEGORIES,
        ]);
    }

    public function receive(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:100000',
            'unit_cost' => 'nullable|numeric|min:0|max:99999999',
            'note' => 'nullable|string|max:255',
        ]);

        return $this->run(fn () => $this->inventory->receive(
            $product, (int) $data['quantity'], isset($data['unit_cost']) ? (float) $data['unit_cost'] : null, $data['note'] ?? null, $request->user()
        ), "Received {$data['quantity']} × {$product->name}.");
    }

    public function writeOff(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:100000',
            'reason' => ['required', Rule::in(StockMovement::WRITE_OFF_REASONS)],
            'note' => 'nullable|string|max:255',
        ]);

        return $this->run(fn () => $this->inventory->writeOff(
            $product, (int) $data['quantity'], $data['reason'], $data['note'] ?? null, $request->user()
        ), "Wrote off {$data['quantity']} × {$product->name} ({$data['reason']}).");
    }

    public function count(Request $request, Product $product)
    {
        $data = $request->validate([
            'counted' => 'required|integer|min:0|max:1000000',
            'note' => 'nullable|string|max:255',
        ]);

        return $this->run(function () use ($product, $data, $request) {
            $movement = $this->inventory->count($product, (int) $data['counted'], $data['note'] ?? null, $request->user());
            $diff = $movement->change === 0 ? 'no difference' : sprintf('%+d', $movement->change);
            session()->flash('success', "Stock count saved for {$product->name}: {$data['counted']} on hand ({$diff}).");
        });
    }

    public function movements(Request $request)
    {
        $type = array_key_exists((string) $request->query('type'), StockMovement::TYPES) ? $request->query('type') : null;
        $product = $request->query('product') ? Product::withTrashed()->find($request->query('product')) : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $movements = StockMovement::with('product', 'user', 'order')
            ->when($product, fn ($q) => $q->where('product_id', $product->id))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('inventory.movements', compact('movements', 'product', 'type', 'from', 'to'));
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ৳ and Bangla text correctly
            fputcsv($out, ['SKU', 'Barcode', 'Product', 'Category', 'Active', 'Price', 'Cost price', 'In stock', 'Reorder level', 'Stock value (cost)', 'Stock value (retail)']);

            Product::with('stock')->orderBy('category')->orderBy('name')->chunk(500, function ($products) use ($out) {
                foreach ($products as $p) {
                    fputcsv($out, [
                        $p->sku, $p->barcode, $p->name, $p->category, $p->is_active ? 'yes' : 'no',
                        $p->price, $p->cost_price, $p->stock_quantity, $p->reorder_level,
                        $p->cost_price !== null ? number_format($p->stock_quantity * $p->cost_price, 2, '.', '') : '',
                        number_format($p->stock_quantity * $p->price, 2, '.', ''),
                    ]);
                }
            });

            fclose($out);
        }, 'inventory_' . now()->format('Y_m_d_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function report()
    {
        $products = Product::with('stock')->orderBy('category')->orderBy('name')->get();
        $currentDateTime = now()->format('d-m-Y H:i:s');

        return Pdf::loadView('admin.stock-report', compact('products', 'currentDateTime'))
            ->download('stock_report_' . now()->format('Y_m_d') . '.pdf');
    }

    private function run(callable $action, ?string $success = null)
    {
        try {
            $action();
        } catch (ShopException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $success ? back()->with('success', $success) : back();
    }
}
