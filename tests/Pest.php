<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** A sellable product with opening stock recorded in the inventory ledger. */
function makeProduct(array $attributes = [], int $stock = 10): App\Models\Product
{
    $product = App\Models\Product::create(array_merge([
        'name' => 'Test Product',
        'description' => 'A product used in tests.',
        'date' => now()->toDateString(),
        'category' => 'Food',
        'price' => 100,
        'cost_price' => 70,
        'reorder_level' => 5,
    ], $attributes));

    app(App\Services\InventoryService::class)->opening($product, $stock);

    return $product->fresh('stock');
}

function customer(): App\Models\User
{
    return App\Models\User::factory()->create(['role' => 'customer']);
}

function staff(string $role = 'employee'): App\Models\User
{
    return App\Models\User::factory()->create(['role' => $role]);
}

function checkoutData(): array
{
    return ['phone' => '01711111111', 'shipping_address' => 'House 1, Road 1, Dhanmondi, Dhaka'];
}

function stockOf(App\Models\Product $product): int
{
    return (int) App\Models\Stock::where('product_id', $product->id)->value('quantity');
}

/** The ledger must always explain the stock level exactly. */
function assertLedgerConsistent(App\Models\Product $product): void
{
    $movements = App\Models\StockMovement::where('product_id', $product->id)->orderBy('id')->get();
    $balance = 0;
    foreach ($movements as $m) {
        $balance += $m->change;
        expect($m->balance_after)->toBe($balance, "Ledger chain broken at movement #{$m->id}");
        expect($balance)->toBeGreaterThanOrEqual(0);
    }
    expect($balance)->toBe(stockOf($product), 'Ledger sum differs from stock level');
}
