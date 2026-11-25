<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use App\Support\Barcode;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        $products = Product::with('stock')
            ->search($search)
            ->inCategory($category)
            ->when($status, fn ($q) => $q->where('is_active', $status === 'active'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'categories' => Product::CATEGORIES,
        ]);
    }

    public function create()
    {
        return view('products.create', ['categories' => Product::CATEGORIES]);
    }

    public function store(Request $request, InventoryService $inventory)
    {
        $this->normalizeCodes($request);

        $validated = $request->validate($this->rules() + [
            'quantity' => 'required|integer|min:0|max:1000000',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = DB::transaction(function () use ($validated, $inventory, $request) {
            $product = Product::create(Arr::except($validated, ['quantity']));
            $inventory->opening($product, (int) $validated['quantity'], $request->user());

            return $product;
        }, 3);

        return redirect()->route('products.index')->with('success', "{$product->name} added (barcode {$product->fresh()->barcode}).");
    }

    public function edit(Product $product)
    {
        $product->load('stock');

        return view('products.edit', ['product' => $product, 'categories' => Product::CATEGORIES]);
    }

    public function update(Request $request, Product $product)
    {
        $this->normalizeCodes($request);

        $validated = $request->validate($this->rules($product));

        // Clearing the barcode or SKU gives the product its generated code back.
        $validated['barcode'] = $validated['barcode'] ?? Barcode::internalFor($product->id);
        $validated['sku'] = $validated['sku'] ?? 'SKU-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT);

        if ($request->hasFile('image')) {
            $this->deleteImage($product);
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return redirect()->route('products.index')->with('success', "{$product->name} updated successfully.");
    }

    /**
     * Soft delete: the product disappears from the shop and the till, but past
     * orders, the stock ledger and its barcode reservation are kept.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', "{$product->name} was removed from the catalogue.");
    }

    /** Pause or resume selling a product without deleting it. */
    public function toggle(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->name . ($product->is_active ? ' is on sale again.' : ' is hidden from the shop and the till.'));
    }

    public function topProducts(Request $request)
    {
        $topProducts = Product::getTopProducts();

        if ($request->query('download') === 'true') {
            $pdf = Pdf::loadView('admin.top_products_report', compact('topProducts'));

            return $pdf->download('top_products_report.pdf');
        }

        return view('admin.top-products', compact('topProducts'));
    }

    public function downloadSeasonalReport(Request $request)
    {
        $request->validate([
            'season' => 'required|string|in:summer,rainy,winter',
            'year' => 'required|integer|between:2000,2100',
        ]);

        $year = (int) $request->year;
        $season = $request->season;

        [$start, $end] = match ($season) {
            'summer' => [Carbon::create($year, 3, 1), Carbon::create($year, 6, 30)],
            'rainy' => [Carbon::create($year, 6, 1), Carbon::create($year, 10, 31)],
            // Winter runs into the next year; endOfMonth() handles leap years.
            'winter' => [Carbon::create($year, 10, 1), Carbon::create($year + 1, 2, 1)->endOfMonth()],
        };

        $products = $this->salesBetween($start->startOfDay(), $end->endOfDay())
            ->addSelect('products.price')
            ->groupBy('products.price')
            ->get();

        $pdf = Pdf::loadView('admin.seasonal_report', [
            'products' => $products,
            'currentDateTime' => now()->format('d-m-Y H:i:s'),
            'season' => ucfirst($season),
            'year' => $year,
            'seasonStart' => $start->format('d M Y'),
            'seasonEnd' => $end->format('d M Y'),
        ]);

        return $pdf->download("seasonal_products_report_{$season}_{$year}.pdf");
    }

    public function downloadMonthlyReport(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $startDate = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $salesData = $this->salesBetween($startDate, $endDate)->get();

        $pdf = Pdf::loadView('admin.monthly_report_pdf', [
            'salesData' => $salesData,
            'month' => $startDate->format('F'),
            'year' => $startDate->format('Y'),
        ]);

        return $pdf->download('monthly_report_' . $startDate->format('M_Y') . '.pdf');
    }

    private function rules(?Product $product = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9\-_.\/]*$/',
                Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:32',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Barcode::isValidGtin($value)) {
                        $fail('The barcode must be an EAN-8, UPC-A (12), EAN-13 or GTIN-14 number with a correct check digit. Leave it empty to generate one.');
                    }
                },
                Rule::unique('products', 'barcode')->ignore($product?->id)],
            'description' => 'required|string|max:5000',
            'date' => 'required|date',
            'image' => 'nullable|image|max:4096',
            'category' => ['required', 'string', Rule::in(Product::CATEGORIES)],
            'price' => 'required|numeric|min:0|max:99999999',
            'cost_price' => 'nullable|numeric|min:0|max:99999999',
            'reorder_level' => 'required|integer|min:0|max:100000',
            'is_active' => 'boolean',
        ];
    }

    /** Scanners and people type codes with spaces/dashes and in lower case. */
    private function normalizeCodes(Request $request): void
    {
        $request->merge([
            'sku' => filled($request->sku) ? strtoupper(trim($request->sku)) : null,
            'barcode' => filled($request->barcode) ? Barcode::normalize($request->barcode) : null,
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    /** Units and revenue per product for non-cancelled orders in a period. */
    private function salesBetween(Carbon $start, Carbon $end)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('orders.status', Order::REVENUE_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                'products.name',
                'products.category',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.category')
            ->orderByDesc('total_quantity');
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image && ! str_starts_with($product->image, 'http')) {
            Storage::disk('public')->delete($product->image);
        }
    }
}
