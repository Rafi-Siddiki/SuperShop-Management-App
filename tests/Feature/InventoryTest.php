<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\InventoryService;

test('receiving stock adds units, logs the delivery and averages the cost', function () {
    $admin = staff('admin');
    $product = makeProduct(['cost_price' => 100], stock: 10);

    $this->actingAs($admin)->post(route('inventory.receive', $product), ['quantity' => 10, 'unit_cost' => 200, 'note' => 'Invoice 42'])
        ->assertSessionHas('success');

    $movement = StockMovement::where('type', 'received')->sole();
    expect(stockOf($product))->toBe(20)
        ->and($movement->change)->toBe(10)
        ->and($movement->balance_after)->toBe(20)
        ->and($movement->user_id)->toBe($admin->id)
        ->and($movement->note)->toBe('Invoice 42')
        ->and((float) $product->fresh()->cost_price)->toBe(150.0); // (10×100 + 10×200) / 20
    assertLedgerConsistent($product);
});

test('write-offs cannot take more than is in stock', function () {
    $product = makeProduct(stock: 3);

    $this->actingAs(staff('admin'))->post(route('inventory.write-off', $product), ['quantity' => 5, 'reason' => 'damaged'])
        ->assertSessionHas('error');
    expect(stockOf($product))->toBe(3);

    $this->actingAs(staff('admin'))->post(route('inventory.write-off', $product), ['quantity' => 2, 'reason' => 'expired', 'note' => 'Past date'])
        ->assertSessionHas('success');
    expect(stockOf($product))->toBe(1)
        ->and(StockMovement::where('type', 'expired')->sole()->change)->toBe(-2);
    assertLedgerConsistent($product);
});

test('write-off reasons are validated', function () {
    $product = makeProduct(stock: 3);

    $this->actingAs(staff('admin'))->post(route('inventory.write-off', $product), ['quantity' => 1, 'reason' => 'sold'])
        ->assertSessionHasErrors('reason');
    expect(stockOf($product))->toBe(3);
});

test('a stock count records the difference, including no difference', function () {
    $product = makeProduct(stock: 10);

    $this->actingAs(staff('admin'))->post(route('inventory.count', $product), ['counted' => 7]);
    $this->actingAs(staff('admin'))->post(route('inventory.count', $product), ['counted' => 7]);

    expect(stockOf($product))->toBe(7)
        ->and(StockMovement::where('type', 'count')->orderBy('id')->pluck('change')->all())->toBe([-3, 0]);
    assertLedgerConsistent($product);
});

test('employees can receive stock but not write off or count', function () {
    $employee = staff('employee');
    $product = makeProduct(stock: 5);

    $this->actingAs($employee)->post(route('inventory.receive', $product), ['quantity' => 4])->assertSessionHas('success');
    $this->actingAs($employee)->post(route('inventory.write-off', $product), ['quantity' => 1, 'reason' => 'lost'])->assertRedirect(route('employee.dashboard'));
    $this->actingAs($employee)->post(route('inventory.count', $product), ['counted' => 0])->assertRedirect(route('employee.dashboard'));

    expect(stockOf($product))->toBe(9);
});

test('employees do not see cost prices in lookups', function () {
    $product = makeProduct(['cost_price' => 55]);

    $this->actingAs(staff('employee'))->getJson(route('barcodes.lookup', ['code' => $product->barcode]))
        ->assertOk()->assertJsonPath('product.cost_price', null);
    $this->actingAs(staff('admin'))->getJson(route('barcodes.lookup', ['code' => $product->barcode]))
        ->assertOk()->assertJsonPath('product.cost_price', 55);
});

test('an order is rejected as a whole when any line is short, and nothing changes', function () {
    $a = makeProduct(['name' => 'Apples'], stock: 5);
    $b = makeProduct(['name' => 'Bananas'], stock: 1);
    $order = Order::create(['user_id' => customer()->id, 'status' => 'pending', 'total_amount' => 0]);

    try {
        app(InventoryService::class)->deductForOrder([$a->id => 2, $b->id => 3], $order, StockMovement::SALE);
        $this->fail('Expected InsufficientStockException');
    } catch (InsufficientStockException $e) {
        expect($e->getMessage())->toContain('Bananas (only 1 left)')
            ->and($e->shortages)->toHaveKey($b->id);
    }

    expect(stockOf($a))->toBe(5)->and(stockOf($b))->toBe(1)
        ->and(StockMovement::whereIn('type', ['sale'])->count())->toBe(0);
});

test('stock and movement pages render for staff', function () {
    $product = makeProduct();

    $this->actingAs(staff('admin'))->get(route('inventory.index'))->assertOk()->assertSee($product->sku);
    $this->actingAs(staff('employee'))->get(route('inventory.index'))->assertOk()->assertDontSee('Stock value at cost');
    $this->actingAs(staff('admin'))->get(route('inventory.movements', ['product' => $product->id]))->assertOk()->assertSee('Opening stock');
    $this->actingAs(staff('admin'))->get(route('inventory.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->actingAs(customer())->get(route('inventory.index'))->assertRedirect(route('customer.dashboard'));
});
