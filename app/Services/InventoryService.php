<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ShopException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Services\Concerns\RetriesTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place stock levels change.
 *
 * Every change:
 *  1. runs in a database transaction (retried if MySQL reports a deadlock),
 *  2. locks the affected stock rows (SELECT … FOR UPDATE) in ascending
 *     product-id order, so concurrent operations queue up instead of
 *     overselling,
 *  3. refuses to take stock below zero (and the column is unsigned as a
 *     last line of defence),
 *  4. writes a ledger row with the resulting balance.
 */
class InventoryService
{
    use RetriesTransactions;

    /**
     * Lock (and create if missing) the stock rows for the given products.
     * Must run inside a transaction.
     *
     * @return Collection<int, Stock> keyed by product id
     */
    public function lock(array $productIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        sort($ids);

        if ($ids === []) {
            return collect();
        }

        $select = fn () => Stock::whereIn('product_id', $ids)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        // Lock existing rows straight away. (Running INSERT IGNORE first would take
        // shared locks that concurrent transactions then deadlock upgrading.)
        $stocks = $select();

        $missing = array_diff($ids, $stocks->keys()->all());
        if ($missing) {
            // Rare: a product without a stock row. The unique index makes concurrent creation safe.
            $now = now();
            Stock::insertOrIgnore(array_map(
                fn ($id) => ['product_id' => $id, 'quantity' => 0, 'created_at' => $now, 'updated_at' => $now],
                array_values($missing)
            ));
            $stocks = $select();
        }

        // INSERT IGNORE also swallows foreign key errors, so verify every row exists.
        if ($stocks->count() !== count($ids)) {
            throw new ShopException('One or more products no longer exist.');
        }

        return $stocks;
    }

    /** Receive a delivery from a supplier. Updates the weighted average cost when a unit cost is given. */
    public function receive(Product $product, int $quantity, ?float $unitCost = null, ?string $note = null, ?User $by = null): StockMovement
    {
        if ($quantity < 1) {
            throw new ShopException('Received quantity must be at least 1.');
        }

        return $this->atomic(function () use ($product, $quantity, $unitCost, $note, $by) {
            $stock = $this->lock([$product->id])[$product->id];

            if ($unitCost !== null) {
                $product = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
                $onHand = max(0, $stock->quantity);
                $oldCost = $product->cost_price !== null ? Money::toPaisa($product->cost_price) : Money::toPaisa($unitCost);
                $average = intdiv($onHand * $oldCost + $quantity * Money::toPaisa($unitCost) + intdiv($onHand + $quantity, 2), $onHand + $quantity);
                $product->forceFill(['cost_price' => Money::fromPaisa($average)])->save();
            }

            return $this->apply($stock, $quantity, StockMovement::RECEIVED, [
                'user_id' => $by?->id,
                'unit_cost' => $unitCost,
                'note' => $note,
            ]);
        });
    }

    /** Write stock off as damaged, expired or lost. */
    public function writeOff(Product $product, int $quantity, string $reason, ?string $note = null, ?User $by = null): StockMovement
    {
        if (! in_array($reason, StockMovement::WRITE_OFF_REASONS, true)) {
            throw new ShopException('Unknown write-off reason.');
        }
        if ($quantity < 1) {
            throw new ShopException('Write-off quantity must be at least 1.');
        }

        return $this->atomic(function () use ($product, $quantity, $reason, $note, $by) {
            $stock = $this->lock([$product->id])[$product->id];

            if ($quantity > $stock->quantity) {
                throw new ShopException("Cannot write off {$quantity}: only {$stock->quantity} of {$product->name} in stock.");
            }

            return $this->apply($stock, -$quantity, $reason, ['user_id' => $by?->id, 'note' => $note]);
        });
    }

    /**
     * Record a physical stock count. The ledger gets the difference between
     * the counted and the recorded quantity (zero differences are recorded too,
     * as proof the count happened).
     */
    public function count(Product $product, int $counted, ?string $note = null, ?User $by = null): StockMovement
    {
        if ($counted < 0) {
            throw new ShopException('Counted quantity cannot be negative.');
        }

        return $this->atomic(function () use ($product, $counted, $note, $by) {
            $stock = $this->lock([$product->id])[$product->id];

            return $this->apply($stock, $counted - $stock->quantity, StockMovement::COUNT, [
                'user_id' => $by?->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Take stock for an order. All lines are checked before anything changes,
     * so an order is either fully stocked or rejected with every shortage listed.
     *
     * @param  array<int, int>  $lines  product id => quantity
     */
    public function deductForOrder(array $lines, Order $order, string $type, ?User $by = null): void
    {
        $this->atomic(function () use ($lines, $order, $type, $by) {
            $stocks = $this->lock(array_keys($lines));

            $shortages = [];
            foreach ($lines as $productId => $quantity) {
                $available = $stocks[$productId]->quantity;
                if ($quantity > $available) {
                    $shortages[$productId] = [
                        'name' => Product::withTrashed()->whereKey($productId)->value('name') ?? "Product #$productId",
                        'requested' => $quantity,
                        'available' => $available,
                    ];
                }
            }

            if ($shortages) {
                throw new InsufficientStockException($shortages);
            }

            foreach ($lines as $productId => $quantity) {
                $this->apply($stocks[$productId], -$quantity, $type, ['order_id' => $order->id, 'user_id' => $by?->id]);
            }
        });
    }

    /** Put an order's items back into stock (cancellation or customer return). */
    public function restoreOrder(Order $order, string $type, ?User $by = null, ?string $note = null): void
    {
        $this->atomic(function () use ($order, $type, $by, $note) {
            $lines = $order->items()->get()
                ->groupBy('product_id')
                ->map(fn ($items) => (int) $items->sum('quantity'))
                ->all();

            $stocks = $this->lock(array_keys($lines));

            foreach ($lines as $productId => $quantity) {
                $this->apply($stocks[$productId], $quantity, $type, [
                    'order_id' => $order->id,
                    'user_id' => $by?->id,
                    'note' => $note,
                ]);
            }
        });
    }

    /** Opening stock for a newly created product. */
    public function opening(Product $product, int $quantity, ?User $by = null): ?StockMovement
    {
        return $this->atomic(function () use ($product, $quantity, $by) {
            $stock = $this->lock([$product->id])[$product->id];

            return $quantity > 0
                ? $this->apply($stock, $quantity, StockMovement::OPENING, ['user_id' => $by?->id, 'unit_cost' => $product->cost_price])
                : null;
        });
    }

    /** Units on hand, stock value at retail and at cost, low and out-of-stock counts. */
    public function summary(): array
    {
        $totals = DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->selectRaw('COALESCE(SUM(stocks.quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(stocks.quantity * products.price), 0) as retail')
            ->selectRaw('COALESCE(SUM(stocks.quantity * products.cost_price), 0) as cost')
            ->selectRaw('SUM(CASE WHEN stocks.quantity > 0 AND stocks.quantity <= products.reorder_level THEN 1 ELSE 0 END) as low')
            ->selectRaw('SUM(CASE WHEN stocks.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->first();

        return [
            'units' => (int) $totals->units,
            'retail' => (float) $totals->retail,
            'cost' => (float) $totals->cost,
            'low' => (int) $totals->low,
            'out' => (int) $totals->out_of_stock,
        ];
    }

    /** Apply a change to an already locked stock row and write the ledger entry. */
    private function apply(Stock $stock, int $change, string $type, array $meta = []): StockMovement
    {
        $balance = $stock->quantity + $change;

        if ($balance < 0) {
            throw new InsufficientStockException([$stock->product_id => [
                'name' => Product::withTrashed()->whereKey($stock->product_id)->value('name') ?? "Product #{$stock->product_id}",
                'requested' => -$change,
                'available' => $stock->quantity,
            ]]);
        }

        $stock->quantity = $balance;
        $stock->save();

        return StockMovement::create([
            'product_id' => $stock->product_id,
            'change' => $change,
            'balance_after' => $balance,
            'type' => $type,
            'order_id' => $meta['order_id'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'unit_cost' => $meta['unit_cost'] ?? null,
            'note' => isset($meta['note']) ? mb_substr($meta['note'], 0, 255) : null,
        ]);
    }
}
