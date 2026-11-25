<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A customer's own orders.
 */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), Order::STATUSES, true) ? $request->query('status') : null;

        $orders = Order::where('user_id', Auth::id())
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with('items.product')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $counts = Order::where('user_id', Auth::id())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('orders.index', compact('orders', 'status', 'counts'));
    }

    public function show(Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 404);

        $order->load('items.product', 'coupon', 'histories');

        return view('orders.show', compact('order'));
    }

    /** Customers can cancel their own online order until staff start packing it. */
    public function destroy(Order $order, OrderService $orders)
    {
        abort_unless($order->user_id === Auth::id(), 404);

        if (! $order->canBeCancelledByCustomer()) {
            return back()->with('error', 'This order is already being prepared and can no longer be cancelled. Please contact us.');
        }

        try {
            // Re-checked under a row lock: if staff confirmed the order meanwhile, this fails.
            $orders->transition($order, Order::STATUS_CANCELLED, Auth::user(), 'Cancelled by the customer', onlyFrom: [Order::STATUS_PENDING]);
        } catch (ShopException $e) {
            return back()->with('error', 'This order is already being prepared and can no longer be cancelled.');
        }

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->number} has been cancelled.");
    }
}
