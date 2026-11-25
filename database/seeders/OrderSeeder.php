<?php

namespace Database\Seeders;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A year of believable trading, fully consistent with the app's rules:
 *  - online orders (cash on delivery) and in-store till sales;
 *  - volume grows over the year with Friday/Saturday peaks and festival spikes;
 *  - every order has a status timeline (audit trail) that matches its status;
 *    cancellations and returns happen at realistic times after the order;
 *  - coupons are used only while live and within their usage limits;
 *  - delivery fees follow the free-delivery threshold;
 *  - the stock ledger is replayed in time order: opening stock, sales,
 *    cancellations, returns, supplier deliveries (whenever stock would fall
 *    below the reorder level), occasional write-offs and stock counts. Each
 *    product's movements add up exactly to its final stock, never below zero.
 */
class OrderSeeder extends Seeder
{
    private const CATEGORY_WEIGHT = [
        'Food' => 3.2, 'Beauty & Health' => 1.4, 'Home & Kitchen' => 1.1, 'Clothing' => 1.2,
        'Books' => 1.0, 'Sports' => 0.8, 'Others' => 0.7, 'Electronics' => 1.0,
    ];

    private const AREAS = [
        ['Dhanmondi', 'Dhaka-1209'], ['Gulshan 2', 'Dhaka-1212'], ['Banani', 'Dhaka-1213'], ['Mirpur 10', 'Dhaka-1216'],
        ['Uttara Sector 7', 'Dhaka-1230'], ['Mohammadpur', 'Dhaka-1207'], ['Bashundhara R/A', 'Dhaka-1229'],
        ['Motijheel', 'Dhaka-1000'], ['Badda', 'Dhaka-1212'], ['Khilgaon', 'Dhaka-1219'], ['Agrabad', 'Chattogram-4100'],
        ['GEC Circle', 'Chattogram-4000'], ['Zindabazar', 'Sylhet-3100'], ['Shaheb Bazar', 'Rajshahi-6100'],
        ['Sonadanga', 'Khulna-9000'], ['Kandirpar', 'Cumilla-3500'],
    ];

    private const WALK_IN_NAMES = ['Rahim', 'Karim', 'Salma', 'Jamal', 'Ruma', 'Sohel', 'Mitu', 'Babul', 'Shapla', 'Hasan'];

    private Carbon $now;
    private array $customers = [];
    private array $staff = ['admin' => [], 'employee' => []];
    private array $products = [];
    private array $coupons = [];
    private array $couponUses = [];
    private array $stockEvents = [];
    private array $histories = [];
    private array $items = [];
    private int $deliveryFee;
    private int $freeThreshold;

    public function run(): void
    {
        $this->now = now();
        $this->deliveryFee = Money::toPaisa(setting('delivery_fee'));
        $this->freeThreshold = Money::toPaisa(setting('free_delivery_threshold'));

        $this->loadPeople();
        $this->loadProducts();
        $this->loadCoupons();

        $start = $this->now->copy()->subDays(364)->startOfDay();
        $counts = ['online' => 0, 'pos' => 0];

        DB::transaction(function () use ($start, &$counts) {
            for ($day = $start->copy(); $day->lte($this->now); $day->addDay()) {
                $t = $start->diffInDays($day) / 364;
                $mult = $this->dayMultiplier($day);

                for ($k = $this->poisson((1.5 + 4.6 * ($t ** 1.4)) * $mult); $k > 0; $k--) {
                    $at = $day->copy()->setTime($this->pickHour(), mt_rand(0, 59), mt_rand(0, 59));
                    if ($at->lt($this->now) && $this->createOnlineOrder($at)) {
                        $counts['online']++;
                    }
                }

                // The till trades 9am–10pm; festivals matter less in-store than online.
                for ($k = $this->poisson((3.0 + 4.0 * $t) * (1 + ($mult - 1) * 0.5)); $k > 0; $k--) {
                    $at = $day->copy()->setTime(mt_rand(9, 21), mt_rand(0, 59), mt_rand(0, 59));
                    if ($at->lt($this->now) && $this->createPosSale($at)) {
                        $counts['pos']++;
                    }
                }

                $this->flush(500);
            }

            // The demo customer always has a fresh order they can still cancel.
            $test = collect($this->customers)->firstWhere('email', 'test@example.com');
            if ($test && $this->createOnlineOrder($this->now->copy()->subMinutes(90), $test, 'pending')) {
                $counts['online']++;
            }

            $this->flush(0);
            $this->replayStockLedger();
        });

        $this->fillCarts();

        $this->command?->info("Created {$counts['online']} online orders and {$counts['pos']} in-store sales.");
    }

    // ------------------------------------------------------------------ orders

    private function createOnlineOrder(Carbon $placedAt, ?array $customer = null, ?string $forceFate = null): bool
    {
        $customer ??= $this->pickCustomer($placedAt);
        if (! $customer) {
            return false;
        }

        $lines = $this->pickLines($placedAt, [1 => 34, 2 => 30, 3 => 18, 4 => 11, 5 => 7], false);
        if (! $lines) {
            return false;
        }

        $subtotal = array_sum(array_map(fn ($l) => $l['price'] * $l['qty'], $lines));
        $coupon = $this->pickCoupon($placedAt, $subtotal, $customer['id'], online: true);
        $discount = $coupon ? Money::percentOf($subtotal, $coupon['discount']) : 0;
        $fee = ($this->freeThreshold > 0 && $subtotal - $discount >= $this->freeThreshold) ? 0 : $this->deliveryFee;

        // Decide the order's fate, then build its timeline and cut it off at "now".
        $fate = $forceFate ?? $this->weighted(['delivered' => 88, 'cancel_pending' => 5, 'cancel_processing' => 2, 'failed_delivery' => 2, 'returned' => 3]);
        $timeline = [['pending', $placedAt->copy(), $customer['id'], 'Order placed online']];
        $confirmAt = $placedAt->copy()->addMinutes(mt_rand(90, 1200));
        $shipAt = $confirmAt->copy()->addMinutes(mt_rand(600, 2000));
        $deliverAt = $shipAt->copy()->addMinutes(mt_rand(1200, 3400));

        if ($fate === 'cancel_pending') {
            $byCustomer = mt_rand(1, 100) <= 60;
            $timeline[] = ['cancelled', $placedAt->copy()->addMinutes(mt_rand(20, 600)), $byCustomer ? $customer['id'] : $this->staffId(),
                $byCustomer ? 'Cancelled by the customer' : 'Customer unreachable by phone'];
        } elseif ($fate !== 'pending') {
            $timeline[] = ['processing', $confirmAt, $this->staffId(), null];
            if ($fate === 'cancel_processing') {
                $timeline[] = ['cancelled', $confirmAt->copy()->addMinutes(mt_rand(30, 400)), $this->staffId(), 'Customer changed their mind'];
            } else {
                $timeline[] = ['shipped', $shipAt, $this->staffId(), null];
                if ($fate === 'failed_delivery') {
                    $timeline[] = ['returned', $shipAt->copy()->addMinutes(mt_rand(1500, 3500)), $this->staffId(), 'Delivery failed — customer refused the parcel'];
                } else {
                    $timeline[] = ['delivered', $deliverAt, $this->staffId(), null];
                    if ($fate === 'returned') {
                        $timeline[] = ['returned', $deliverAt->copy()->addMinutes(mt_rand(1440, 8000)), $this->staffId(), 'Wrong size / not as expected'];
                    }
                }
            }
        }

        $timeline = array_values(array_filter($timeline, fn ($step) => $step[1]->lte($this->now)));
        $status = end($timeline)[0];

        $paymentStatus = match (true) {
            $status === 'delivered' => 'paid',
            $status === 'cancelled' => 'void',
            $status === 'returned' => collect($timeline)->contains(fn ($s) => $s[0] === 'delivered') ? 'refunded' : 'void',
            default => 'unpaid',
        };

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $customer['id'],
            'status' => $status,
            'channel' => 'online',
            'payment_method' => 'cod',
            'payment_status' => $paymentStatus,
            'subtotal' => Money::fromPaisa($subtotal),
            'discount_amount' => Money::fromPaisa($discount),
            'delivery_fee' => Money::fromPaisa($fee),
            'total_amount' => Money::fromPaisa($subtotal - $discount + $fee),
            'coupon_id' => $coupon['id'] ?? null,
            'phone' => $customer['phone'],
            'shipping_address' => $customer['address'],
            'created_at' => $placedAt,
            'updated_at' => end($timeline)[1],
        ]);

        $this->recordCouponUse($coupon, $customer['id'], $status);
        $this->addItems($orderId, $lines, $placedAt);
        $this->addTimeline($orderId, $timeline);

        // Stock: taken when placed, returned on cancellation / failed delivery / most returns.
        foreach ($lines as $l) {
            $this->stockEvent($placedAt, $l['id'], -$l['qty'], 'sale', $orderId, $customer['id']);
        }
        $last = end($timeline);
        $restock = $last[0] === 'cancelled' || ($last[0] === 'returned' && mt_rand(1, 100) <= 80);
        if ($restock) {
            foreach ($lines as $l) {
                $this->stockEvent($last[1], $l['id'], $l['qty'], $last[0] === 'cancelled' ? 'cancelled' : 'returned', $orderId, $last[2]);
            }
        } elseif ($last[0] === 'returned') {
            // The returned entry is the last history row buffered for this order.
            $this->histories[array_key_last($this->histories)][4] = 'Returned damaged — items not restocked';
        }

        return true;
    }

    private function createPosSale(Carbon $soldAt): bool
    {
        $lines = $this->pickLines($soldAt, [1 => 30, 2 => 28, 3 => 20, 4 => 12, 5 => 6, 6 => 4], true);
        if (! $lines) {
            return false;
        }

        $subtotal = array_sum(array_map(fn ($l) => $l['price'] * $l['qty'], $lines));
        $coupon = mt_rand(1, 100) <= 30 ? $this->pickCoupon($soldAt, $subtotal, null, online: false) : null;
        $discount = $coupon ? Money::percentOf($subtotal, $coupon['discount']) : 0;
        $total = $subtotal - $discount;

        $method = $this->weighted(['cash' => 60, 'card' => 15, 'bkash' => 18, 'nagad' => 7]);
        $tendered = $method === 'cash' ? $this->cashTendered($total) : $total;
        $cashier = $this->staffId(preferEmployee: true);

        $returned = mt_rand(1, 1000) <= 8 && $soldAt->copy()->addDays(2)->lt($this->now);
        $returnAt = $returned ? $soldAt->copy()->addMinutes(mt_rand(600, 7000)) : null;
        if ($returnAt && $returnAt->gt($this->now)) {
            $returned = false;
        }

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => null,
            'served_by' => $cashier,
            'customer_name' => mt_rand(1, 100) <= 15 ? self::WALK_IN_NAMES[array_rand(self::WALK_IN_NAMES)] : null,
            'status' => $returned ? 'returned' : 'delivered',
            'channel' => 'pos',
            'payment_method' => $method,
            'payment_status' => $returned ? 'refunded' : 'paid',
            'subtotal' => Money::fromPaisa($subtotal),
            'discount_amount' => Money::fromPaisa($discount),
            'delivery_fee' => '0.00',
            'total_amount' => Money::fromPaisa($total),
            'amount_tendered' => Money::fromPaisa($tendered),
            'change_due' => Money::fromPaisa($tendered - $total),
            'coupon_id' => $coupon['id'] ?? null,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => $soldAt,
            'updated_at' => $returnAt ?? $soldAt,
        ]);

        $this->recordCouponUse($coupon, null, 'delivered');
        $this->addItems($orderId, $lines, $soldAt);

        $timeline = [['delivered', $soldAt, $cashier, 'Sold at the counter']];
        if ($returned) {
            $timeline[] = ['returned', $returnAt, $this->staffId(), 'Customer returned the items'];
        }
        $this->addTimeline($orderId, $timeline, firstFrom: null);

        foreach ($lines as $l) {
            $this->stockEvent($soldAt, $l['id'], -$l['qty'], 'pos_sale', $orderId, $cashier);
            if ($returned) {
                $this->stockEvent($returnAt, $l['id'], $l['qty'], 'returned', $orderId, $timeline[1][2]);
            }
        }

        return true;
    }

    private function pickLines(Carbon $at, array $lineCounts, bool $inStore): array
    {
        $available = array_filter($this->products, fn ($p) => $p['active'] && $p['listed']->lte($at));
        if (count($available) < 10) {
            return [];
        }

        // In the shop, food and household goods dominate even more.
        $weights = [];
        foreach ($available as $id => $p) {
            $weights[$id] = $p['weight'] * ($inStore && in_array($p['category'], ['Food', 'Home & Kitchen', 'Beauty & Health'], true) ? 1.6 : 1);
        }

        $count = $this->weighted($lineCounts);
        $chosen = [];
        for ($guard = 0; count($chosen) < $count && $guard < 50; $guard++) {
            $chosen[$this->weighted($weights)] = true;
        }

        return array_map(function ($id) {
            $p = $this->products[$id];
            $qty = $p['category'] === 'Food' ? $this->weighted([1 => 45, 2 => 30, 3 => 15, 4 => 10]) : $this->weighted([1 => 85, 2 => 15]);

            return ['id' => $id, 'qty' => $qty, 'price' => $p['price'], 'cost' => $p['cost']];
        }, array_keys($chosen));
    }

    private function pickCoupon(Carbon $at, int $subtotal, ?int $customerId, bool $online): ?array
    {
        $live = array_filter($this->coupons, function ($c) use ($at, $subtotal, $customerId) {
            $used = $this->couponUses[$c['id']] ?? ['total' => 0, 'by' => []];

            return $at->between($c['from'], $c['to'])
                && ($c['min'] === null || $subtotal >= $c['min'])
                && ($c['max'] === null || $used['total'] < $c['max'])
                && ($c['per'] === null || $customerId === null || ($used['by'][$customerId] ?? 0) < $c['per']);
        });

        // Festival codes get used a lot; the evergreen welcome code rarely.
        if (! $live || mt_rand(1, 100) > (count($live) > 1 || ! $online ? 40 : 12)) {
            return null;
        }

        usort($live, fn ($a, $b) => $b['discount'] <=> $a['discount']);

        return $live[0];
    }

    private function recordCouponUse(?array $coupon, ?int $customerId, string $status): void
    {
        if (! $coupon || $status === 'cancelled') {
            return; // cancelled orders give the use back
        }
        $this->couponUses[$coupon['id']]['total'] = ($this->couponUses[$coupon['id']]['total'] ?? 0) + 1;
        if ($customerId) {
            $this->couponUses[$coupon['id']]['by'][$customerId] = ($this->couponUses[$coupon['id']]['by'][$customerId] ?? 0) + 1;
        }
    }

    private function addItems(int $orderId, array $lines, Carbon $at): void
    {
        foreach ($lines as $l) {
            $this->items[] = [
                'order_id' => $orderId, 'product_id' => $l['id'], 'quantity' => $l['qty'],
                'price' => Money::fromPaisa($l['price']), 'cost' => Money::fromPaisa($l['cost']),
                'created_at' => $at, 'updated_at' => $at,
            ];
        }
    }

    /** @param  array<int, array{0: string, 1: Carbon, 2: ?int, 3: ?string}>  $timeline */
    private function addTimeline(int $orderId, array $timeline, ?string $firstFrom = null): void
    {
        $from = $firstFrom;
        foreach ($timeline as $i => [$status, $at, $userId, $note]) {
            $this->histories[] = [$orderId, $i === 0 ? $firstFrom : $from, $status, $userId, $note, $at];
            $from = $status;
        }
    }

    private function flush(int $threshold): void
    {
        if (count($this->items) > $threshold) {
            foreach (array_chunk($this->items, 500) as $chunk) {
                DB::table('order_items')->insert($chunk);
            }
            $this->items = [];
        }

        if (count($this->histories) > $threshold) {
            foreach (array_chunk($this->histories, 500) as $chunk) {
                DB::table('order_status_histories')->insert(array_map(fn ($h) => [
                    'order_id' => $h[0], 'from_status' => $h[1], 'to_status' => $h[2],
                    'user_id' => $h[3], 'note' => $h[4], 'created_at' => $h[5],
                ], $chunk));
            }
            $this->histories = [];
        }
    }

    // ------------------------------------------------------------------- stock

    private function stockEvent(Carbon $at, int $productId, int $change, string $type, ?int $orderId, ?int $userId): void
    {
        $this->stockEvents[$productId][] = [$at->timestamp, $change, $type, $orderId, $userId, null, null];
    }

    /**
     * Replay each product's events in time order, adding supplier deliveries
     * before stock would run short, then a few write-offs and stock counts.
     */
    private function replayStockLedger(): void
    {
        $ids = array_keys($this->products);
        shuffle($ids);
        $endOut = array_flip(array_slice($ids, 0, 4));   // finish the year sold out
        $endLow = array_flip(array_slice($ids, 4, 8));   // finish at or below the reorder level
        $rows = [];
        $finals = [];

        foreach ($this->products as $id => $p) {
            $events = $this->stockEvents[$id] ?? [];
            usort($events, fn ($a, $b) => $a[0] <=> $b[0]);

            $time = $p['listed']->copy()->setTime(9, 0)->timestamp;
            $restockQty = fn () => match (true) {
                $p['category'] === 'Food' => mt_rand(60, 160),
                $p['price'] > 5_000_000 => mt_rand(4, 8),
                $p['price'] > 1_000_000 => mt_rand(8, 16),
                default => mt_rand(20, 60),
            };
            $cutoff = $this->now->timestamp - 30 * 86400;
            $balance = $restockQty();
            $rows[] = $this->movement($id, $time, $balance, $balance, 'opening', null, $this->staffId(adminOnly: true), $p['cost'], 'Opening stock');

            // Movement times per product are kept strictly increasing, so each
            // balance_after matches the order the rows are read back in.
            foreach ($events as [$at, $change, $type, $orderId, $userId]) {
                if ($change < 0 && $balance + $change < $p['reorder']) {
                    // Sold-out / low products stop being restocked in the last month (unless stock would go negative).
                    $hold = (isset($endOut[$id]) || isset($endLow[$id])) && $at > $cutoff && $balance + $change >= 0;
                    if (! $hold) {
                        $qty = $restockQty();
                        while ($balance + $qty + $change < $p['reorder']) {
                            $qty += $restockQty();
                        }
                        $receivedAt = max($time + 1, $at - mt_rand(2, 30) * 3600);
                        $balance += $qty;
                        $unitCost = (int) round($p['cost'] * mt_rand(97, 103) / 100);
                        $rows[] = $this->movement($id, $receivedAt, $qty, $balance, 'received', null, $this->staffId(), $unitCost, 'Supplier delivery');
                        $time = $receivedAt;
                    }
                }

                // Occasional breakage or expiry found on the shelf.
                if (mt_rand(1, 1000) <= 4 && $balance >= 3) {
                    $lost = mt_rand(1, min(3, $balance - 1));
                    $balance -= $lost;
                    $reason = $p['category'] === 'Food' ? 'expired' : 'damaged';
                    $time = max($time + 1, $at - 1800);
                    $rows[] = $this->movement($id, $time, -$lost, $balance, $reason, null, $this->staffId(adminOnly: true), null, $reason === 'expired' ? 'Past best-before date' : 'Broken packaging');
                }

                $balance += $change;
                $time = max($time + 1, $at);
                $rows[] = $this->movement($id, $time, $change, $balance, $type, $orderId, $userId, null, null);
            }

            // Finish the designated products sold out / low with a stock count.
            $target = isset($endOut[$id]) ? 0 : (isset($endLow[$id]) ? mt_rand(1, max(1, $p['reorder'] - 1)) : null);
            if ($target !== null && $balance > $target) {
                $countAt = min($this->now->timestamp - 60, max($time + 3600, $this->now->timestamp - mt_rand(1, 3) * 86400));
                $diff = $target - $balance;
                $balance = $target;
                $time = max($time + 1, $countAt);
                $rows[] = $this->movement($id, $time, $diff, $balance, 'count', null, $this->staffId(adminOnly: true), null, 'Monthly stock count — shrinkage');
            }

            $finals[$id] = $balance;
        }

        // Insert in global time order so the movement log reads chronologically.
        usort($rows, fn ($a, $b) => [$a['created_at'], $a['product_id'], $a['_seq']] <=> [$b['created_at'], $b['product_id'], $b['_seq']]);
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('stock_movements')->insert(array_map(function ($r) {
                unset($r['_seq']);
                $r['created_at'] = Carbon::createFromTimestamp($r['created_at'])->setTimezone(config('app.timezone'));

                return $r;
            }, $chunk));
        }

        foreach ($finals as $id => $qty) {
            DB::table('stocks')->where('product_id', $id)->update(['quantity' => $qty, 'updated_at' => $this->now]);
        }
    }

    private int $seq = 0;

    private function movement(int $productId, int $at, int $change, int $balance, string $type, ?int $orderId, ?int $userId, ?int $unitCostPaisa, ?string $note): array
    {
        if ($balance < 0) {
            throw new \LogicException("Seeder produced negative stock for product $productId.");
        }

        return [
            'product_id' => $productId, 'change' => $change, 'balance_after' => $balance, 'type' => $type,
            'order_id' => $orderId, 'user_id' => $userId,
            'unit_cost' => $unitCostPaisa !== null ? Money::fromPaisa($unitCostPaisa) : null,
            'note' => $note, 'created_at' => $at, '_seq' => $this->seq++,
        ];
    }

    // ---------------------------------------------------------------- loading

    private function loadPeople(): void
    {
        foreach (DB::table('users')->get(['id', 'email', 'role', 'created_at']) as $row) {
            if ($row->role === 'customer') {
                [$area, $city] = self::AREAS[array_rand(self::AREAS)];
                $this->customers[$row->id] = [
                    'id' => $row->id,
                    'email' => $row->email,
                    'joined' => Carbon::parse($row->created_at),
                    // Pareto-ish loyalty: a few regulars place most of the orders.
                    'weight' => $row->email === 'test@example.com' ? 4.0 : min(6.0, 1 / ((mt_rand(1, 1000) / 1000) ** 0.75)),
                    'phone' => '01' . mt_rand(3, 9) . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'address' => 'House ' . mt_rand(1, 120) . ', Road ' . mt_rand(1, 32) . ", $area, $city",
                ];
            } else {
                $this->staff[$row->role][] = $row->id;
            }
        }
    }

    private function loadProducts(): void
    {
        foreach (DB::table('products')->get(['id', 'category', 'price', 'cost_price', 'reorder_level', 'is_active', 'date']) as $row) {
            $price = Money::toPaisa($row->price);
            $appeal = exp($this->gaussian() * 0.6);
            $priceDamp = 1 / (1 + ($price / 600_000) ** 1.1);

            $this->products[$row->id] = [
                'id' => $row->id,
                'category' => $row->category,
                'price' => $price,
                'cost' => Money::toPaisa($row->cost_price),
                'reorder' => (int) $row->reorder_level,
                'active' => (bool) $row->is_active,
                'listed' => Carbon::parse($row->date),
                'weight' => (self::CATEGORY_WEIGHT[$row->category] ?? 1) * $appeal * $priceDamp,
            ];
        }
    }

    private function loadCoupons(): void
    {
        $this->coupons = DB::table('coupons')->get()->map(fn ($c) => [
            'id' => $c->id,
            'discount' => (float) $c->discount,
            'min' => $c->min_order_amount !== null ? Money::toPaisa($c->min_order_amount) : null,
            'max' => $c->max_uses,
            'per' => $c->max_uses_per_customer,
            'from' => Carbon::parse($c->created_at),
            'to' => Carbon::parse($c->expiry_date),
        ])->all();
    }

    private function pickCustomer(Carbon $at): ?array
    {
        $eligible = array_filter($this->customers, fn ($c) => $c['joined']->lte($at));

        return $eligible ? $this->customers[$this->weighted(array_column($eligible, 'weight', 'id'))] : null;
    }

    private function staffId(bool $preferEmployee = false, bool $adminOnly = false): ?int
    {
        $pool = $adminOnly ? $this->staff['admin'] : ($preferEmployee && $this->staff['employee'] ? $this->staff['employee'] : array_merge($this->staff['admin'], $this->staff['employee']));

        return $pool ? $pool[array_rand($pool)] : null;
    }

    // ----------------------------------------------------------------- helpers

    private function dayMultiplier(Carbon $day): float
    {
        $mult = match ($day->dayOfWeek) {
            Carbon::FRIDAY => 1.35,
            Carbon::SATURDAY => 1.2,
            default => 1.0,
        };

        $md = $day->format('m-d');
        $ymd = $day->toDateString();
        if ($ymd >= '2026-03-14' && $ymd <= '2026-03-20') $mult *= 2.3;   // before Eid ul-Fitr
        if ($ymd >= '2026-05-20' && $ymd <= '2026-05-26') $mult *= 1.7;   // before Eid ul-Adha
        if ($md >= '04-10' && $md <= '04-14') $mult *= 1.5;               // Pohela Boishakh
        if ($md >= '12-26' || $md <= '01-03') $mult *= 1.4;               // year end
        if ($md >= '11-10' && $md <= '11-12') $mult *= 2.6;               // 11.11 sale

        return $mult;
    }

    /** Customers mostly order in the evening. */
    private function pickHour(): int
    {
        return $this->weighted([9 => 3, 10 => 5, 11 => 6, 12 => 6, 13 => 5, 14 => 5, 15 => 5, 16 => 6, 17 => 7,
            18 => 8, 19 => 9, 20 => 10, 21 => 10, 22 => 8, 23 => 4]);
    }

    /** What a customer hands over: exact, or the next convenient note. */
    private function cashTendered(int $total): int
    {
        $step = $this->weighted([1000 => 15, 5000 => 25, 10000 => 25, 50000 => 20, 100000 => 15]);

        return (int) (ceil($total / $step) * $step);
    }

    private function fillCarts(): void
    {
        $now = $this->now;
        $inStock = DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->where('stocks.quantity', '>', 5)
            ->where('products.is_active', true)
            ->where('products.price', '<', 6000)
            ->pluck('stocks.product_id')
            ->all();

        $customers = collect($this->customers)->shuffle()->take(8)
            ->push(collect($this->customers)->firstWhere('email', 'test@example.com'))
            ->filter()->unique('id');

        foreach ($customers as $customer) {
            $cartId = DB::table('carts')->insertGetId([
                'user_id' => $customer['id'], 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);

            $count = $customer['email'] === 'test@example.com' ? 3 : mt_rand(1, 3);
            foreach ((array) array_rand(array_flip($inStock), $count) as $productId) {
                DB::table('cart_items')->insert([
                    'cart_id' => $cartId, 'product_id' => $productId, 'quantity' => mt_rand(1, 2),
                    'price' => Money::fromPaisa($this->products[$productId]['price']),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** Pick a key from [key => weight]. */
    private function weighted(array $weights): int|string
    {
        $total = array_sum($weights);
        $r = mt_rand() / mt_getrandmax() * $total;

        foreach ($weights as $key => $weight) {
            $r -= $weight;
            if ($r <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function poisson(float $lambda): int
    {
        $l = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $l);

        return $k - 1;
    }

    private function gaussian(): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
