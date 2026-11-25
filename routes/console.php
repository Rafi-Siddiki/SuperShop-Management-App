<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/*
| Load the demo shop (staff, customers, ~150 products, a year of orders) into
| an EMPTY database. Safe to run on every start: it does nothing once the shop
| has users or products, so it can never duplicate or overwrite real data.
*/
Artisan::command('shop:seed-demo', function () {
    if (User::query()->exists() || Product::withTrashed()->exists()) {
        $this->components->info('The database already has data — demo data not loaded.');

        return 0;
    }

    $this->components->info('Loading demo data (downloads ~150 product photos on first run)…');

    return $this->call('db:seed', ['--force' => true]);
})->purpose('Load demo data into an empty database');
