<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;

class HomeController extends Controller
{
    /**
     * Public storefront landing page.
     */
    public function index()
    {
        $bestSellerIds = Product::getTopProducts(8)->pluck('id');

        $bestSellers = Product::with('stock')
            ->active()
            ->whereIn('id', $bestSellerIds)
            ->get()
            ->sortBy(fn ($product) => $bestSellerIds->search($product->id))
            ->values();

        $newArrivals = Product::with('stock')->active()->latest('date')->latest('id')->take(8)->get();

        $categories = Product::query()
            ->active()
            ->selectRaw('category, COUNT(*) as product_count')
            ->groupBy('category')
            ->orderByDesc('product_count')
            ->get();

        $featuredCoupon = Coupon::active()->orderByDesc('discount')->first();

        return view('welcome', compact('bestSellers', 'newArrivals', 'categories', 'featuredCoupon'));
    }
}
