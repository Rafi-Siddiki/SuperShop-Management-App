<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopException;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Barcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * In-store till. The browser only sends product ids and quantities; prices,
 * discounts, totals and change are calculated on the server.
 */
class PosController extends Controller
{
    public function index()
    {
        $todaySales = Order::where('channel', Order::CHANNEL_POS)
            ->revenue()
            ->where('served_by', auth()->id())
            ->where('created_at', '>=', today())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        $recent = Order::where('channel', Order::CHANNEL_POS)
            ->where('served_by', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        return view('pos.index', compact('todaySales', 'recent'));
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'payment_method' => ['required', Rule::in(Order::POS_PAYMENT_METHODS)],
            'amount_tendered' => 'nullable|numeric|min:0|max:100000000',
            'customer_name' => 'nullable|string|max:120',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'coupon' => 'nullable|string|max:50',
            'idempotency_key' => 'required|string|min:16|max:64|regex:/^[A-Za-z0-9\-]+$/',
        ]);

        try {
            $order = $orders->posSale($request->user(), $data['items'], [
                'method' => $data['payment_method'],
                'tendered' => $data['amount_tendered'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'coupon' => $data['coupon'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);
        } catch (ShopException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'total' => (float) $order->total_amount,
                'change' => (float) $order->change_due,
            ],
            'receipt_url' => route('pos.receipt', $order),
        ]);
    }

    /** Server-calculated totals for the basket on screen (nothing is saved). */
    public function quote(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'items' => 'present|array|max:200',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'coupon' => 'nullable|string|max:50',
        ]);

        return response()->json($orders->posQuote($data['items'], $data['coupon'] ?? null));
    }

    /** 80 mm thermal receipt (also used to reprint). */
    public function receipt(Order $order)
    {
        $order->load('items.product', 'servedBy', 'coupon', 'user');

        $vatRate = (float) setting('vat_rate');
        $vatIncluded = $vatRate > 0 ? round((float) $order->total_amount * $vatRate / (100 + $vatRate), 2) : 0;

        return view('pos.receipt', [
            'order' => $order,
            'vatRate' => $vatRate,
            'vatIncluded' => $vatIncluded,
            'orderBarcode' => Barcode::code128Svg($order->number, 1, 34),
            'autoprint' => request()->boolean('print'),
        ]);
    }
}
