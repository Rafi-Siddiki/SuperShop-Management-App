<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

test('checkout calculates totals on the server and applies the coupon', function () {
    $user = customer();
    $product = makeProduct(['price' => 250], stock: 10);
    Coupon::create(['name' => 'TEN', 'discount' => 10, 'expiry_date' => now()->addDay()]);

    $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 2]);
    $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'TEN']);

    // A tampered total_amount from the browser must be ignored.
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData() + ['total_amount' => 1])
        ->assertRedirect();

    // 500 - 10% = 450 of goods, below the free-delivery threshold, so the default ৳60 fee applies.
    $order = Order::sole();
    expect((float) $order->subtotal)->toBe(500.0)
        ->and((float) $order->discount_amount)->toBe(50.0)
        ->and((float) $order->delivery_fee)->toBe(60.0)
        ->and((float) $order->total_amount)->toBe(510.0)
        ->and($order->status)->toBe('pending')
        ->and($product->stock->fresh()->quantity)->toBe(8);
});

test('customers cannot add more than the available stock', function () {
    $user = customer();
    $product = makeProduct(stock: 2);

    $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 3])
        ->assertSessionHas('error');

    expect(Cart::where('user_id', $user->id)->first()?->items()->count() ?? 0)->toBe(0);
});

test('customers cannot touch other customers cart items, orders or invoices', function () {
    $owner = customer();
    $intruder = customer();
    $product = makeProduct();

    $this->actingAs($owner)->post(route('cart.add', $product));
    $item = Cart::where('user_id', $owner->id)->first()->items()->first();

    $this->actingAs($intruder)->post(route('cart.remove', $item))->assertNotFound();
    $this->actingAs($intruder)->post(route('cart.update', $item), ['action' => 'increase'])->assertNotFound();

    $this->actingAs($owner)->post(route('cart.checkout'), checkoutData());
    $order = Order::sole();

    $this->actingAs($intruder)->get(route('orders.show', $order))->assertNotFound();
    $this->actingAs($intruder)->get(route('invoice.show', $order))->assertNotFound();
    $this->actingAs($owner)->get(route('invoice.show', $order))->assertOk();
});

test('invoices require login', function () {
    $this->get('/invoice/1')->assertRedirect(route('login'));
});

test('cancelling a pending order returns its items to stock', function () {
    $user = customer();
    $product = makeProduct(stock: 5);

    $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 3]);
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());
    expect($product->stock->fresh()->quantity)->toBe(2);

    $order = Order::sole();
    $this->actingAs($user)->delete(route('orders.destroy', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($product->stock->fresh()->quantity)->toBe(5);
});

test('staff move orders forward but cannot skip steps', function () {
    $employee = User::factory()->create(['role' => 'employee']);
    $order = Order::create(['user_id' => customer()->id, 'status' => 'pending', 'total_amount' => 100]);

    $this->actingAs($employee)->patch(route('manage.orders.status', $order), ['status' => 'delivered'])
        ->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('pending');

    $this->actingAs($employee)->patch(route('manage.orders.status', $order), ['status' => 'processing']);
    expect($order->fresh()->status)->toBe('processing');
});

test('each role is kept to its own area', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create(['role' => 'employee']);
    $customer = customer();

    $this->actingAs($customer)->get(route('admin.dashboard'))->assertRedirect(route('customer.dashboard'));
    $this->actingAs($customer)->get(route('manage.orders.index'))->assertRedirect(route('customer.dashboard'));
    $this->actingAs($employee)->get(route('products.index'))->assertRedirect(route('employee.dashboard'));
    $this->actingAs($employee)->get(route('manage.orders.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.sales-reports'))->assertOk();
    $this->actingAs($admin)->get(route('products.top'))->assertOk();
});

test('deleting a product keeps past orders intact', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = customer();
    $product = makeProduct();

    $this->actingAs($user)->post(route('cart.add', $product));
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    $this->actingAs($admin)->delete(route('products.destroy', $product))->assertRedirect();

    $order = Order::sole();
    expect($order->items()->count())->toBe(1);
    $this->actingAs($user)->get(route('orders.show', $order))->assertOk()->assertSee('Test Product');
});

test('the shop, product page and dashboards render', function () {
    $product = makeProduct();

    $this->get('/')->assertOk();
    $this->actingAs(customer())->get(route('customer.dashboard'))->assertOk()->assertSee('Test Product');
    $this->actingAs(customer())->get(route('shop.product', $product))->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'employee']))->get(route('employee.dashboard'))->assertOk();
});
