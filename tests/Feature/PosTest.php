<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Str;

function sale(array $items, array $overrides = []): array
{
    return array_merge([
        'items' => $items,
        'payment_method' => 'cash',
        'amount_tendered' => 1000,
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

test('a till sale prices from the database, takes stock and computes change', function () {
    $cashier = staff('employee');
    $milk = makeProduct(['name' => 'Milk', 'price' => 95.50], stock: 10);
    $eggs = makeProduct(['name' => 'Eggs', 'price' => 150], stock: 10);

    $response = $this->actingAs($cashier)->postJson(route('pos.store'), sale([
        ['product_id' => $milk->id, 'quantity' => 2, 'price' => 1],   // a client-sent price is ignored
        ['product_id' => $eggs->id, 'quantity' => 1],
        ['product_id' => $milk->id, 'quantity' => 1],                   // repeated scan merges into one line
    ], ['amount_tendered' => 500]))->assertOk();

    $order = Order::sole();
    expect((float) $order->total_amount)->toBe(436.5)       // 3 × 95.50 + 150
        ->and((float) $order->change_due)->toBe(63.5)
        ->and($order->channel)->toBe('pos')
        ->and($order->status)->toBe('delivered')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->served_by)->toBe($cashier->id)
        ->and($order->user_id)->toBeNull()
        ->and($order->items)->toHaveCount(2)
        ->and(stockOf($milk))->toBe(7)
        ->and(stockOf($eggs))->toBe(9)
        ->and(StockMovement::where('type', 'pos_sale')->count())->toBe(2);
    $response->assertJsonPath('order.change', 63.5);
    assertLedgerConsistent($milk);
});

test('cash below the total is refused and nothing is saved', function () {
    $product = makeProduct(['price' => 300], stock: 5);

    $this->actingAs(staff())->postJson(route('pos.store'), sale([['product_id' => $product->id, 'quantity' => 2]], ['amount_tendered' => 500]))
        ->assertStatus(422)->assertJsonFragment(['message' => 'Cash received (৳500) is less than the total (৳600).']);

    expect(Order::count())->toBe(0)->and(stockOf($product))->toBe(5);
});

test('card and mobile payments are charged the exact total', function () {
    $product = makeProduct(['price' => 120], stock: 5);

    $this->actingAs(staff())->postJson(route('pos.store'), sale([['product_id' => $product->id, 'quantity' => 1]], ['payment_method' => 'bkash', 'amount_tendered' => null]))
        ->assertOk();

    $order = Order::sole();
    expect($order->payment_method)->toBe('bkash')->and((float) $order->amount_tendered)->toBe(120.0)->and((float) $order->change_due)->toBe(0.0);
});

test('the same sale submitted twice is recorded once', function () {
    $product = makeProduct(stock: 10);
    $payload = sale([['product_id' => $product->id, 'quantity' => 3]]);

    $first = $this->actingAs(staff())->postJson(route('pos.store'), $payload)->assertOk()->json('order.id');
    $second = $this->actingAs(staff())->postJson(route('pos.store'), $payload)->assertOk()->json('order.id');

    expect($second)->toBe($first)->and(Order::count())->toBe(1)->and(stockOf($product))->toBe(7);
});

test('inactive, deleted and short-stocked products cannot be sold', function () {
    $inactive = makeProduct(['is_active' => false]);
    $deleted = makeProduct();
    $deleted->delete();
    $short = makeProduct(['name' => 'Rice'], stock: 1);

    $this->actingAs(staff())->postJson(route('pos.store'), sale([['product_id' => $inactive->id, 'quantity' => 1]]))->assertStatus(422);
    $this->actingAs(staff())->postJson(route('pos.store'), sale([['product_id' => $deleted->id, 'quantity' => 1]]))->assertStatus(422);
    $this->actingAs(staff())->postJson(route('pos.store'), sale([['product_id' => $short->id, 'quantity' => 2]]))
        ->assertStatus(422)->assertJsonFragment(['message' => 'Not enough stock for Rice (only 1 left).']);

    expect(Order::count())->toBe(0)->and(stockOf($short))->toBe(1);
});

test('the quote shows server totals, coupon discounts and stock problems', function () {
    $product = makeProduct(['price' => 1000], stock: 2);
    Coupon::create(['name' => 'TILL10', 'discount' => 10, 'min_order_amount' => 1500, 'expiry_date' => now()->addDay()]);

    $this->actingAs(staff())->postJson(route('pos.quote'), ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'coupon' => 'till10'])
        ->assertOk()->assertJsonPath('total', 1000)->assertJsonPath('coupon_error', 'This coupon needs a minimum order of ৳1,500.');

    $this->actingAs(staff())->postJson(route('pos.quote'), ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'coupon' => 'till10'])
        ->assertOk()->assertJsonPath('discount', 200)->assertJsonPath('total', 1800)->assertJsonPath('problems', []);

    $this->actingAs(staff())->postJson(route('pos.quote'), ['items' => [['product_id' => $product->id, 'quantity' => 3]]])
        ->assertOk()->assertJsonPath('problems.0', 'Test Product: only 2 in stock.');
});

test('a till sale can be returned with or without restocking', function () {
    $admin = staff('admin');
    $product = makeProduct(stock: 10);

    foreach ([true, false] as $restock) {
        $id = $this->actingAs($admin)->postJson(route('pos.store'), sale([['product_id' => $product->id, 'quantity' => 2]]))->json('order.id');
        $this->actingAs($admin)->patch(route('manage.orders.status', $id), ['status' => 'returned', 'restock' => $restock ? '1' : '0', 'note' => 'Faulty'])
            ->assertSessionHas('success');

        $order = Order::find($id);
        expect($order->status)->toBe('returned')->and($order->payment_status)->toBe('refunded');
    }

    // 10 − 2 + 2 (restocked) − 2 (not restocked) = 8
    expect(stockOf($product))->toBe(8)->and(StockMovement::where('type', 'returned')->count())->toBe(1);
    assertLedgerConsistent($product);
});

test('receipts render with the store details and customers cannot use the till', function () {
    $product = makeProduct();
    $id = $this->actingAs(staff())->postJson(route('pos.store'), sale([['product_id' => $product->id, 'quantity' => 1]]))->json('order.id');

    $this->actingAs(staff())->get(route('pos.receipt', $id))->assertOk()->assertSee(setting('store_name'))->assertSee('SS-' . str_pad($id, 6, '0', STR_PAD_LEFT));
    $this->actingAs(customer())->get(route('pos.index'))->assertRedirect(route('customer.dashboard'));
    $this->actingAs(customer())->postJson(route('pos.store'), sale([['product_id' => $product->id, 'quantity' => 1]]))->assertForbidden();
});
