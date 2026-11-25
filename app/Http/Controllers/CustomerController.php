<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    private const SORTS = [
        'popular' => 'Most popular',
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name' => 'Name A–Z',
    ];

    /**
     * Shop front: searchable, filterable product catalogue.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'popular';

        $query = Product::with('stock')
            ->active()
            ->search($search)
            ->inCategory($category)
            ->withSum(['orderItems as units_sold' => fn ($q) => $q->whereHas(
                'order', fn ($o) => $o->revenue()
            )], 'quantity');

        match ($sort) {
            'newest' => $query->latest('date')->latest('id'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('units_sold')->orderBy('name'),
        };

        $products = $query->paginate(24)->withQueryString();

        $categories = Product::query()
            ->active()
            ->selectRaw('category, COUNT(*) as product_count')
            ->groupBy('category')
            ->orderBy('category')
            ->pluck('product_count', 'category');

        return view('customer.dashboard', [
            'products' => $products,
            'categories' => $categories,
            'sorts' => self::SORTS,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
        ]);
    }

    /**
     * Product detail page with related products.
     */
    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load('stock');

        $unitsSold = (int) $product->orderItems()
            ->whereHas('order', fn ($o) => $o->revenue())
            ->sum('quantity');

        $related = Product::with('stock')
            ->active()
            ->where('category', $product->category)
            ->whereKeyNot($product->id)
            ->inRandomOrder()
            ->take(4)
            ->get();

        return view('customer.product', compact('product', 'unitsSold', 'related'));
    }
}
