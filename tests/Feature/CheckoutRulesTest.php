<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\OrderService;

function fillCart($user, $product, int $quantity = 1): void
{
    test()->actingAs($user)->post(route('cart.add', $product), ['quantity' => $quantity]);
}

test('submitting checkout twice creates one order', function () {
    $user = customer();
    $product = makeProduct(stock: 10);
    fillCart($user, $product, 2);

    $this->actingAs($user)->post(route('cart.checkout'), checkoutData())->assertRedirect();
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData())->assertSessionHas('error', 'Your cart is empty.');

    expect(Order::count())->toBe(1)->and(stockOf($product))->toBe(8);
    assertLedgerConsistent($product);
});

test('free delivery applies from the threshold, using the configured fee', function () {
    Setting::store(['delivery_fee' => 80, 'free_delivery_threshold' => 1000]);
    $user = customer();

    fillCart($user, makeProduct(['price' => 999]));
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());
    fillCart($user, makeProduct(['price' => 1000]));
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    expect(Order::orderBy('id')->pluck('delivery_fee')->map(fn ($f) => (float) $f)->all())->toBe([80.0, 0.0])
        ->and((float) Order::first()->total_amount)->toBe(1079.0);
});

test('coupon total usage limit holds, and a cancelled order gives its use back', function () {
    $coupon = Coupon::create(['name' => 'ONCE', 'discount' => 10, 'max_uses' => 1, 'expiry_date' => now()->addDay()]);
    [$alice, $bob] = [customer(), customer()];
    $product = makeProduct(stock: 10);

    // Both apply the coupon while it is still unused…
    foreach ([$alice, $bob] as $user) {
        fillCart($user, $product);
        $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'once'])->assertSessionHas('success');
    }

    // …so the limit must be enforced again at checkout. (Tests share one session
    // across users, so each checkout carries its own coupon explicitly.)
    $this->actingAs($alice)->withSession(['coupon_id' => $coupon->id])->post(route('cart.checkout'), checkoutData());
    $this->actingAs($bob)->withSession(['coupon_id' => $coupon->id])->post(route('cart.checkout'), checkoutData())
        ->assertSessionHas('error', 'This coupon has reached its usage limit.');
    expect(Order::count())->toBe(1);

    // Alice cancels: the use is released and Bob can now check out with it.
    $this->actingAs($alice)->delete(route('orders.destroy', Order::sole()));
    $this->actingAs($bob)->withSession(['coupon_id' => $coupon->id])->post(route('cart.checkout'), checkoutData())->assertRedirect();
    expect(Order::where('user_id', $bob->id)->sole()->coupon_id)->toBe($coupon->id);
});

test('coupon per-customer limit and minimum order are enforced', function () {
    Coupon::create(['name' => 'FIRST', 'discount' => 10, 'max_uses_per_customer' => 1, 'min_order_amount' => 300, 'expiry_date' => now()->addDay()]);
    $user = customer();
    $product = makeProduct(['price' => 200], stock: 10);

    fillCart($user, $product);
    $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'FIRST'])
        ->assertSessionHas('error', 'This coupon needs a minimum order of ৳300.');

    fillCart($user, $product); // now ৳400
    $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'FIRST'])->assertSessionHas('success');
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    fillCart($user, $product, 2);
    $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'FIRST'])
        ->assertSessionHas('error', 'You have already used this coupon the maximum number of times.');
});

test('paused and expired coupons are refused', function () {
    Coupon::create(['name' => 'PAUSED', 'discount' => 10, 'is_active' => false, 'expiry_date' => now()->addDay()]);
    Coupon::create(['name' => 'OLD', 'discount' => 10, 'expiry_date' => now()->subMinute()]);
    $user = customer();
    fillCart($user, makeProduct());

    $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'PAUSED'])->assertSessionHas('error', 'This coupon is not active.');
    $this->actingAs($user)->post(route('cart.applyCoupon'), ['coupon_code' => 'OLD'])->assertSessionHas('error', 'This coupon has expired.');
});

test('a product deactivated while in the cart cannot be checked out', function () {
    $user = customer();
    $product = makeProduct(stock: 5);
    fillCart($user, $product);
    $product->update(['is_active' => false]);

    $this->actingAs($user)->post(route('cart.checkout'), checkoutData())->assertSessionHas('error');
    expect(Order::count())->toBe(0)->and(stockOf($product))->toBe(5);

    // Viewing the cart removes it and explains why.
    $this->actingAs($user)->get(route('cart.index'))->assertOk()->assertSee('no longer sold');
    expect(Cart::where('user_id', $user->id)->first()->items()->count())->toBe(0);
});

test('price changes after adding to the cart are honoured at checkout', function () {
    $user = customer();
    $product = makeProduct(['price' => 100]);
    fillCart($user, $product);
    $product->update(['price' => 120]);

    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    expect((float) Order::sole()->items->first()->price)->toBe(120.0);
});

test('cash on delivery is marked paid on delivery and orders keep a full timeline', function () {
    $user = customer();
    $employee = staff();
    fillCart($user, makeProduct());
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());
    $order = Order::sole();

    foreach (['processing', 'shipped', 'delivered'] as $status) {
        $this->actingAs($employee)->patch(route('manage.orders.status', $order), ['status' => $status])->assertSessionHas('success');
    }

    $order->refresh();
    expect($order->payment_status)->toBe('paid')
        ->and($order->histories->pluck('to_status')->all())->toBe(['pending', 'processing', 'shipped', 'delivered'])
        ->and($order->histories->last()->user_id)->toBe($employee->id);
});

test('a stale screen cannot apply a transition that is no longer allowed', function () {
    $user = customer();
    $product = makeProduct(stock: 5);
    fillCart($user, $product, 2);
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    $staleCopy = Order::sole();                       // loaded while still pending
    app(OrderService::class)->transition(Order::sole(), 'cancelled', $user);   // someone else cancels

    // The stale copy still says "pending", but the service re-reads the row under lock.
    expect(fn () => app(OrderService::class)->transition($staleCopy, 'processing', staff()))
        ->toThrow(\App\Exceptions\ShopException::class, 'is cancelled and cannot be marked processing');

    expect(stockOf($product))->toBe(5)
        ->and(StockMovement::where('type', 'cancelled')->count())->toBe(1);  // restocked exactly once
    assertLedgerConsistent($product);
});

test('customers cannot cancel once packing has started', function () {
    $user = customer();
    fillCart($user, makeProduct());
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());
    $order = Order::sole();
    $this->actingAs(staff())->patch(route('manage.orders.status', $order), ['status' => 'processing']);

    $this->actingAs($user)->delete(route('orders.destroy', $order))->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('processing');
});

test('returning a delivered online order refunds it and restocks when asked', function () {
    $user = customer();
    $admin = staff('admin');
    $product = makeProduct(stock: 5);
    fillCart($user, $product, 2);
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());
    $order = Order::sole();
    foreach (['processing', 'shipped', 'delivered', 'returned'] as $status) {
        $this->actingAs($admin)->patch(route('manage.orders.status', $order), ['status' => $status, 'restock' => '1']);
    }

    expect($order->fresh()->status)->toBe('returned')
        ->and($order->fresh()->payment_status)->toBe('refunded')
        ->and(stockOf($product))->toBe(5);
    assertLedgerConsistent($product);
});

test('deleting a customer keeps their orders for the books', function () {
    $user = customer();
    fillCart($user, makeProduct());
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect(Order::count())->toBe(1)->and(Order::sole()->user_id)->toBeNull();
    $this->actingAs(staff('admin'))->get(route('manage.orders.show', Order::sole()))->assertOk();
});

test('a customer cancel loaded before staff confirmed is refused under the lock', function () {
    $user = customer();
    $product = makeProduct(stock: 5);
    fillCart($user, $product);
    $this->actingAs($user)->post(route('cart.checkout'), checkoutData());

    $customersView = Order::sole();                                            // still "pending" on the customer's screen
    app(OrderService::class)->transition(Order::sole(), 'processing', staff()); // staff confirm first

    expect(fn () => app(OrderService::class)->transition($customersView, 'cancelled', $user, onlyFrom: ['pending']))
        ->toThrow(\App\Exceptions\ShopException::class);
    expect(Order::sole()->status)->toBe('processing')->and(stockOf($product))->toBe(4);
});
