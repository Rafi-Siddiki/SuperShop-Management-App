<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CouponSeeder extends Seeder
{
    /**
     * Each coupon is "live" from created_at until expiry_date; OrderSeeder only
     * applies a coupon inside that window and within its limits.
     */
    public function run(): void
    {
        $now = now();

        $coupons = [
            // code, %, starts, expires, min order, max uses, per customer
            ['WELCOME10', 10, $now->copy()->subDays(400), $now->copy()->addMonths(6), 500, null, 1],
            ['NEWYEAR26', 20, Carbon::create(2025, 12, 28), Carbon::create(2026, 1, 5, 23, 59), 1000, null, 2],
            ['EID25', 25, Carbon::create(2026, 3, 14), Carbon::create(2026, 3, 22, 23, 59), 1500, 300, 1],
            ['BOISHAKH14', 14, Carbon::create(2026, 4, 10), Carbon::create(2026, 4, 16, 23, 59), null, null, null],
            ['QURBANI15', 15, Carbon::create(2026, 5, 20), Carbon::create(2026, 5, 28, 23, 59), 1000, null, 2],
            ['SALE1111', 30, Carbon::create(2026, 11, 10), Carbon::create(2026, 11, 12, 23, 59), 2000, 100, 1],
            ['WINTER20', 20, $now->copy()->subDays(8), $now->copy()->addDays(40), 1500, 500, 3],
            ['FLASH15', 15, $now->copy()->subDays(1), $now->copy()->addDays(4), null, 50, 1],
        ];

        DB::table('coupons')->insert(array_map(fn ($c) => [
            'name' => $c[0],
            'discount' => $c[1],
            'min_order_amount' => $c[4],
            'max_uses' => $c[5],
            'max_uses_per_customer' => $c[6],
            'is_active' => true,
            'expiry_date' => $c[3],
            'created_at' => $c[2],
            'updated_at' => $c[2],
        ], $coupons));
    }
}
