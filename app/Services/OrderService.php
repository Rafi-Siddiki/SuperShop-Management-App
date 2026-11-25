<?php

namespace App\Services;

use App\Exceptions\ShopException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Services\Concerns\RetriesTransactions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Placing orders (online checkout and the in-store till) and moving them
 * through their lifecycle. Totals are always computed here from current
 * product prices, never taken from the browser.
 *
 * Lock order is always: cart → coupon → order → stock rows (ascending),
 * so concurrent requests queue instead of deadlocking; if MySQL still picks
 * a deadlock victim, the transaction is retried (RetriesTransactions).
 */
class OrderService
{
    use RetriesTransactions;

    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Turn a customer's cart into an order.
     *
     * The cart row is locked first: a double-submitted checkout waits for the
     * first one, then finds the cart empty instead of creating a second order.
     */
    public function checkout(User $customer, array $delivery, ?int $couponId = null): Order
    {
        return $this->atomic(function () use ($customer, $delivery, $couponId) {
            $cart = Cart::where('user_id', $customer->id)->where('status', 'active')->lockForUpdate()->first();
            $items = $cart ? $cart->items()->get() : collect();

            if ($items->isEmpty()) {
                throw new ShopException('Your cart is empty.');
            }

            $lines = $this->priceLines($items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity]));
            $subtotal = $lines->sum('line_paisa');

            $coupon = $couponId ? $this->lockCoupon($couponId, $customer, $subtotal) : null;
            $discount = $coupon ? Money::percentOf($subtotal, $coupon->discount) : 0;
            $deliveryFee = $this->deliveryFeeFor($subtotal - $discount);

            $order = Order::create([
                'user_id' => $customer->id,
                'status' => Order::STATUS_PENDING,
                'channel' => Order::CHANNEL_ONLINE,
                'payment_method' => 'cod',
                'payment_status' => Order::PAYMENT_UNPAID,
                'subtotal' => Money::fromPaisa($subtotal),
                'discount_amount' => Money::fromPaisa($discount),
                'delivery_fee' => Money::fromPaisa($deliveryFee),
                'total_amount' => Money::fromPaisa($subtotal - $discount + $deliveryFee),
                'coupon_id' => $coupon?->id,
                'phone' => $delivery['phone'],
                'shipping_address' => $delivery['shipping_address'],
                'notes' => $delivery['notes'] ?? null,
            ]);

            $this->createItemsAndDeductStock($order, $lines, StockMovement::SALE, $customer);
            $cart->items()->delete();
            $this->record($order, null, Order::STATUS_PENDING, $customer, 'Order placed online');

            return $order;
        });
    }

    /**
     * Price a till basket without saving anything, so the cashier sees the
     * exact total (with coupon) before taking payment. Uses the same pricing
     * code as posSale(); stock is re-checked under lock when the sale is made.
     */
    public function posQuote(array $lines, ?string $couponCode): array
    {
        $merged = $this->mergeLines($lines);
        $result = ['subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'coupon' => null, 'coupon_error' => null, 'problems' => [], 'lines' => []];

        if ($merged->isEmpty()) {
            return $result;
        }

        try {
            $priced = $this->priceLines($merged);
        } catch (ShopException $e) {
            $result['problems'][] = $e->getMessage();

            return $result;
        }

        $subtotal = $priced->sum('line_paisa');
        $stocks = \App\Models\Stock::whereIn('product_id', $priced->pluck('product.id'))->pluck('quantity', 'product_id');

        foreach ($priced as $line) {
            $available = (int) ($stocks[$line['product']->id] ?? 0);
            if ($line['quantity'] > $available) {
                $result['problems'][] = "{$line['product']->name}: only {$available} in stock.";
            }
            $result['lines'][] = ['product_id' => $line['product']->id, 'price' => $line['price_paisa'] / 100, 'stock' => $available];
        }

        $discount = 0;
        if (filled($couponCode)) {
            $coupon = Coupon::where('name', strtoupper(trim($couponCode)))->first();
            $problem = $coupon ? $coupon->problemFor(null, $subtotal / 100) : 'Coupon code not found.';
            if ($problem) {
                $result['coupon_error'] = $problem;
            } else {
                $discount = Money::percentOf($subtotal, $coupon->discount);
                $result['coupon'] = ['code' => $coupon->name, 'label' => $coupon->discount_label];
            }
        }

        return array_merge($result, [
            'subtotal' => $subtotal / 100,
            'discount' => $discount / 100,
            'total' => ($subtotal - $discount) / 100,
        ]);
    }

    /**
     * Ring up an in-store sale.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     * @param  array{method: string, tendered?: float|string|null, idempotency_key: string, customer_name?: ?string, phone?: ?string, coupon?: ?string}  $payment
     */
    public function posSale(User $cashier, array $lines, array $payment): Order
    {
        $key = $payment['idempotency_key'];

        // Same key = same sale (double click, retried request): return it instead of selling twice.
        if ($existing = Order::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        if (! in_array($payment['method'], Order::POS_PAYMENT_METHODS, true)) {
            throw new ShopException('Choose a payment method.');
        }

        try {
            return $this->atomic(function () use ($cashier, $lines, $payment, $key) {
                $merged = $this->mergeLines($lines);

                if ($merged->isEmpty()) {
                    throw new ShopException('Scan at least one item.');
                }

                $priced = $this->priceLines($merged);
                $subtotal = $priced->sum('line_paisa');

                $coupon = null;
                if (filled($payment['coupon'] ?? null)) {
                    $found = Coupon::where('name', strtoupper(trim($payment['coupon'])))->first();
                    if (! $found) {
                        throw new ShopException('Coupon code not found.');
                    }
                    $coupon = $this->lockCoupon($found->id, null, $subtotal);
                }

                $discount = $coupon ? Money::percentOf($subtotal, $coupon->discount) : 0;
                $total = $subtotal - $discount;

                if ($payment['method'] === 'cash') {
                    $tendered = Money::toPaisa($payment['tendered'] ?? 0);
                    if ($tendered < $total) {
                        throw new ShopException('Cash received (' . money(Money::fromPaisa($tendered)) . ') is less than the total (' . money(Money::fromPaisa($total)) . ').');
                    }
                } else {
                    $tendered = $total; // card / mobile wallet: exact amount is charged
                }

                $order = Order::create([
                    'user_id' => null,
                    'served_by' => $cashier->id,
                    'customer_name' => filled($payment['customer_name'] ?? null) ? trim($payment['customer_name']) : null,
                    'phone' => filled($payment['phone'] ?? null) ? trim($payment['phone']) : null,
                    'status' => Order::STATUS_DELIVERED,
                    'channel' => Order::CHANNEL_POS,
                    'payment_method' => $payment['method'],
                    'payment_status' => Order::PAYMENT_PAID,
                    'subtotal' => Money::fromPaisa($subtotal),
                    'discount_amount' => Money::fromPaisa($discount),
                    'delivery_fee' => '0.00',
                    'total_amount' => Money::fromPaisa($total),
                    'amount_tendered' => Money::fromPaisa($tendered),
                    'change_due' => Money::fromPaisa($tendered - $total),
                    'coupon_id' => $coupon?->id,
                    'idempotency_key' => $key,
                ]);

                $this->createItemsAndDeductStock($order, $priced, StockMovement::POS_SALE, $cashier);
                $this->record($order, null, Order::STATUS_DELIVERED, $cashier, 'Sold at the counter');

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced past the first check; the other one won.
            return Order::where('idempotency_key', $key)->firstOr(fn () => throw $e);
        }
    }

    /**
     * Move an order to a new status, with its side effects:
     *  - cancelled: stock goes back, coupon use is released, payment voided/refunded;
     *  - delivered: cash on delivery is marked paid;
     *  - returned: stock goes back if $restock, paid orders are marked refunded.
     *
     * The order row is locked and the transition re-checked, so two staff
     * clicking at once (or a customer cancelling while staff ship) cannot
     * both succeed. $onlyFrom narrows the allowed starting statuses further,
     * e.g. customers may only cancel while the order is still pending.
     */
    public function transition(Order $order, string $to, ?User $by, ?string $note = null, bool $restock = true, ?array $onlyFrom = null): Order
    {
        return $this->atomic(function () use ($order, $to, $by, $note, $restock, $onlyFrom) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! in_array($to, $locked->nextStatuses(), true) || ($onlyFrom !== null && ! in_array($from, $onlyFrom, true))) {
                throw new ShopException("Order {$locked->number} is {$from} and cannot be marked {$to}.");
            }

            $changes = ['status' => $to];

            if ($to === Order::STATUS_CANCELLED) {
                $this->inventory->restoreOrder($locked, StockMovement::CANCELLED, $by, $note);
                $changes['payment_status'] = $locked->payment_status === Order::PAYMENT_PAID ? Order::PAYMENT_REFUNDED : Order::PAYMENT_VOID;
            }

            if ($to === Order::STATUS_DELIVERED && $locked->payment_status === Order::PAYMENT_UNPAID) {
                $changes['payment_status'] = Order::PAYMENT_PAID;
            }

            if ($to === Order::STATUS_RETURNED) {
                if ($restock) {
                    $this->inventory->restoreOrder($locked, StockMovement::RETURNED, $by, $note);
                }
                $changes['payment_status'] = $locked->payment_status === Order::PAYMENT_PAID ? Order::PAYMENT_REFUNDED : Order::PAYMENT_VOID;
            }

            $locked->update($changes);

            $this->record($locked, $from, $to, $by, $note ?: ($to === Order::STATUS_RETURNED && ! $restock ? 'Items not returned to stock' : null));

            return $locked;
        });
    }

    /** Delivery fee for an order whose goods (after discount) cost $goodsPaisa. */
    public function deliveryFeeFor(int $goodsPaisa): int
    {
        $threshold = setting('free_delivery_threshold');

        if ($threshold > 0 && $goodsPaisa >= Money::toPaisa($threshold)) {
            return 0;
        }

        return Money::toPaisa(setting('delivery_fee'));
    }

    /**
     * Price lines from current product data and check they can be sold.
     *
     * @param  Collection<int, array{product_id: int, quantity: int}>  $lines
     * @return Collection<int, array{product: Product, quantity: int, price_paisa: int, line_paisa: int}>
     */
    private function priceLines(Collection $lines): Collection
    {
        $products = Product::withTrashed()->whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

        return $lines->map(function ($line) use ($products) {
            $product = $products->get($line['product_id']);
            $quantity = (int) $line['quantity'];

            if (! $product || ! $product->is_sellable) {
                throw new ShopException(($product?->name ?? 'A product') . ' is no longer available. Please remove it and try again.');
            }

            if ($quantity < 1 || $quantity > 999) {
                throw new ShopException("Invalid quantity for {$product->name}.");
            }

            $price = Money::toPaisa($product->price);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'price_paisa' => $price,
                'line_paisa' => $price * $quantity,
            ];
        })->values();
    }

    /** Combine repeated scans of the same product into one line. */
    private function mergeLines(array $lines): Collection
    {
        return collect($lines)
            ->groupBy(fn ($l) => (int) $l['product_id'])
            ->map(fn ($group, $productId) => ['product_id' => (int) $productId, 'quantity' => (int) collect($group)->sum('quantity')])
            ->values();
    }

    /** Lock the coupon row and validate it with locking reads (usage limits hold under concurrency). */
    private function lockCoupon(int $couponId, ?User $customer, int $subtotalPaisa): Coupon
    {
        $coupon = Coupon::whereKey($couponId)->lockForUpdate()->first();

        if (! $coupon) {
            throw new ShopException('This coupon no longer exists.');
        }

        if ($problem = $coupon->problemFor($customer, $subtotalPaisa / 100, lockingReads: true)) {
            throw new ShopException($problem);
        }

        return $coupon;
    }

    private function createItemsAndDeductStock(Order $order, Collection $lines, string $movementType, ?User $by): void
    {
        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'price' => Money::fromPaisa($line['price_paisa']),
                'cost' => $line['product']->cost_price,
            ]);
        }

        $this->inventory->deductForOrder(
            $lines->mapWithKeys(fn ($l) => [$l['product']->id => $l['quantity']])->all(),
            $order,
            $movementType,
            $by
        );
    }

    private function record(Order $order, ?string $from, string $to, ?User $by, ?string $note): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $by?->id,
            'note' => $note,
        ]);
    }
}
