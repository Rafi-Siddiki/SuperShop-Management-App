<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a realistic demo store: staff, ~65 customers, ~150 products with
     * real photos, coupons and a year of order history.
     *
     *   php artisan migrate:fresh --seed
     *
     * Demo logins (password "password"): admin@example.com,
     * employee@example.com and test@example.com (customer).
     */
    public function run(): void
    {
        // Fixed seed: the same data shape on every run (dates stay relative to today).
        mt_srand(20241201);

        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            CouponSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
