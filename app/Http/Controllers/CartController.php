<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public const MAX_PER_LINE = 99;

    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index()
    {
        $cart = $this->activeCart()->load(['items.product' => fn ($q) => $q->withTrashed()->with('stock')]);

        // Drop items that can no longer be bought (deleted or deactivated products).
        $unavailable = $cart->items->filter(fn ($item) => ! $item->product?->is_sellable);
        if ($unavailable->isNotEmpty()) {
            CartItem::whereIn('id', $unavailable->pluck('id'))->delete();
            $cart->setRelation('items', $cart->items->diff($unavailable)->values());
            session()->now('error', 'Removed from your cart because they are no longer sold: '
                . $unavailable->map(fn ($i) => $i->product?->name ?? 'a product')->implode(', ') . '.');
        }

        $subtotal = Money::toPaisa($cart->items->sum(fn ($i) => $i->product->price * $i->quantity));

        $coupon = $this->sessionCoupon();
        $couponProblem = $coupon?->problemFor(Auth::user(), $subtotal / 100);
        $discount = $coupon && ! $couponProblem ? Money::percentOf($subtotal, $coupon->discount) : 0;
        $deliveryFee = $cart->items->isEmpty() ? 0 : $this->orders->deliveryFeeFor($subtotal - $discount);

        $threshold = Money::toPaisa(setting('free_delivery_threshold'));
        $toFreeDelivery = $threshold > 0 ? max(0, $threshold - ($subtotal - $discount)) : null;

        $lastOrder = Auth::user()->orders()->whereNotNull('shipping_address')->latest()->first();

        return view('cart.index', [
            'cart' => $cart,
            'coupon' => $coupon,
            'couponProblem' => $couponProblem,
            'subtotal' => $subtotal / 100,
            'discount' => $discount / 100,
            'deliveryFee' => $deliveryFee / 100,
            'total' => ($subtotal - $discount + $deliveryFee) / 100,
            'toFreeDelivery' => $toFreeDelivery !== null ? $toFreeDelivery / 100 : null,
            'lastOrder' => $lastOrder,
        ]);
    }

    public function addToCart(Request $request, Product $product)
    {
        $data = $request->validate(['quantity' => 'nullable|integer|min:1|max:' . self::MAX_PER_LINE]);
        $quantity = (int) ($data['quantity'] ?? 1);

        if (! $product->is_active) {
            return back()->with('error', "{$product->name} is not available right now.");
        }

        $product->load('stock');
        $available = $product->stock_quantity;

        if ($available <= 0) {
            return back()->with('error', "{$product->name} is out of stock.");
        }

        $error = DB::transaction(function () use ($product, $quantity, $available) {
            // Lock the cart so two tabs adding at once update one line instead of colliding.
            $cart = Cart::whereKey($this->activeCart()->id)->lockForUpdate()->first();
            $item = $cart->items()->where('product_id', $product->id)->first();
            $newQuantity = ($item?->quantity ?? 0) + $quantity;

            if ($newQuantity > min($available, self::MAX_PER_LINE)) {
                $limit = min($available, self::MAX_PER_LINE);

                return $item
                    ? "You already have {$item->quantity} in your cart; the most you can order is {$limit}."
                    : "Only {$limit} of {$product->name} can be ordered.";
            }

            $item
                ? $item->update(['quantity' => $newQuantity, 'price' => $product->price])
                : $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price]);

            return null;
        }, 3);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', "{$product->name} added to your cart.");
    }

    public function updateQuantity(Request $request, CartItem $item)
    {
        $this->authorizeItem($item);
        $request->validate(['action' => 'required|in:increase,decrease']);

        if ($request->action === 'increase') {
            $item->load('product.stock');
            if ($item->quantity + 1 > min($item->product->stock_quantity, self::MAX_PER_LINE)) {
                return back()->with('error', "No more {$item->product->name} available.");
            }
            $item->increment('quantity');
        } elseif ($item->quantity > 1) {
            $item->decrement('quantity');
        } else {
            $item->delete();
        }

        return redirect()->route('cart.index');
    }

    public function removeProduct(CartItem $item)
    {
        $this->authorizeItem($item);
        $item->delete();

        return redirect()->route('cart.index')->with('success', 'Item removed from your cart.');
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['coupon_code' => 'required|string|max:50']);

        $coupon = Coupon::where('name', strtoupper(trim($request->coupon_code)))->first();
        if (! $coupon) {
            return redirect()->route('cart.index')->with('error', 'Invalid coupon code.');
        }

        $cart = $this->activeCart()->load('items.product');
        $subtotal = (float) $cart->items->sum(fn ($i) => ($i->product?->price ?? 0) * $i->quantity);

        if ($problem = $coupon->problemFor(Auth::user(), $subtotal)) {
            return redirect()->route('cart.index')->with('error', $problem);
        }

        session(['coupon_id' => $coupon->id]);

        return redirect()->route('cart.index')->with('success', "{$coupon->discount_label} discount applied.");
    }

    public function removeCoupon()
    {
        session()->forget('coupon_id');

        return redirect()->route('cart.index')->with('success', 'Coupon removed.');
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'shipping_address' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $order = $this->orders->checkout(Auth::user(), $data, session('coupon_id'));
        } catch (ShopException $e) {
            return redirect()->route('cart.index')->withInput()->with('error', $e->getMessage());
        }

        session()->forget('coupon_id');

        return redirect()->route('orders.show', $order)
            ->with('success', "Order {$order->number} placed successfully! We'll start packing it soon.");
    }

    private function activeCart(): Cart
    {
        // firstOrCreate falls back to a re-read if a concurrent request created the cart first.
        return Cart::firstOrCreate(['user_id' => Auth::id(), 'status' => 'active']);
    }

    private function sessionCoupon(): ?Coupon
    {
        return session('coupon_id') ? Coupon::find(session('coupon_id')) : null;
    }

    private function authorizeItem(CartItem $item): void
    {
        abort_unless($item->cart && $item->cart->user_id === Auth::id(), 404);
    }
}
