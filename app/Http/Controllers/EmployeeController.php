<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;

class EmployeeController extends Controller
{
    public function index()
    {
        $counts = Order::where('channel', Order::CHANNEL_ONLINE)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $queue = Order::with('user')
            ->withCount('items')
            ->where('channel', Order::CHANNEL_ONLINE)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING])
            // Orders waiting for confirmation come first, then packing; oldest first within each.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Order::STATUS_PENDING])
            ->oldest()
            ->take(12)
            ->get();

        $shippedToday = Order::whereHas('histories', fn ($h) => $h
            ->where('to_status', Order::STATUS_SHIPPED)
            ->where('created_at', '>=', today()))
            ->count();

        $mySalesToday = Order::where('channel', Order::CHANNEL_POS)->revenue()
            ->where('served_by', auth()->id())->where('created_at', '>=', today())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total')->first();

        $lowStock = Product::with('stock')->active()->needsReorder()->get()->sortBy('stock_quantity')->take(8);

        return view('employee.dashboard', compact('counts', 'queue', 'shippedToday', 'mySalesToday', 'lowStock'));
    }
}
