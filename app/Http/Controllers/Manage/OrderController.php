<?php

namespace App\Http\Controllers\Manage;

use App\Exceptions\ShopException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Order fulfilment for admins and employees.
 */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), Order::STATUSES, true) ? $request->query('status') : null;
        $channel = array_key_exists((string) $request->query('channel'), Order::CHANNELS) ? $request->query('channel') : null;
        $search = trim((string) $request->query('q', ''));

        $orders = Order::with('user', 'servedBy')
            ->withCount('items')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->when($search !== '', function ($q) use ($search) {
                $id = (int) preg_replace('/\D/', '', $search);
                $q->where(function ($q) use ($search, $id) {
                    $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                    if ($id > 0) {
                        $q->orWhere('id', $id);
                    }
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = Order::when($channel, fn ($q) => $q->where('channel', $channel))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('manage.orders.index', compact('orders', 'status', 'channel', 'search', 'counts'));
    }

    public function show(Order $order)
    {
        $order->load('items.product', 'user', 'coupon', 'servedBy', 'histories.user');

        return view('manage.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'note' => 'nullable|string|max:255',
            'restock' => 'nullable|boolean',
        ]);

        try {
            $order = $orders->transition($order, $data['status'], $request->user(), $data['note'] ?? null, $request->boolean('restock', true));
        } catch (ShopException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order {$order->number} marked as {$order->status}.");
    }
}
