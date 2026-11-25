<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Barcode;
use Illuminate\Http\UploadedFile;

function productForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Soap', 'description' => 'Bath soap', 'date' => now()->toDateString(), 'category' => 'Beauty & Health',
        'price' => 75, 'cost_price' => 55, 'reorder_level' => 10, 'quantity' => 20, 'is_active' => '1',
    ], $overrides);
}

test('a new product gets a SKU, a valid internal barcode and an opening stock entry', function () {
    $this->actingAs(staff('admin'))->post(route('products.store'), productForm())->assertRedirect(route('products.index'));

    $product = Product::sole();
    expect($product->sku)->toBe('SKU-' . str_pad($product->id, 5, '0', STR_PAD_LEFT))
        ->and($product->barcode)->toBe(Barcode::internalFor($product->id))
        ->and(stockOf($product))->toBe(20)
        ->and(StockMovement::sole()->type)->toBe('opening');
});

test('manufacturer barcodes are validated, normalised and unique', function () {
    $admin = staff('admin');

    $this->actingAs($admin)->post(route('products.store'), productForm(['barcode' => '5901234123450']))->assertSessionHasErrors('barcode');
    $this->actingAs($admin)->post(route('products.store'), productForm(['barcode' => '5901-234 123457', 'sku' => 'soap-100g']))->assertSessionHasNoErrors();
    expect(Product::sole()->barcode)->toBe('5901234123457')->and(Product::sole()->sku)->toBe('SOAP-100G');

    $this->actingAs($admin)->post(route('products.store'), productForm(['barcode' => '5901234123457']))->assertSessionHasErrors('barcode');
    $this->actingAs($admin)->post(route('products.store'), productForm(['sku' => 'SOAP-100G']))->assertSessionHasErrors('sku');

    // A deleted product keeps its barcode reserved, so old labels never scan as a different product.
    Product::sole()->delete();
    $this->actingAs($admin)->post(route('products.store'), productForm(['barcode' => '5901234123457']))->assertSessionHasErrors('barcode');
});

test('clearing the barcode on edit gives the product its internal code back', function () {
    $product = makeProduct(['barcode' => '4006381333931']);

    $this->actingAs(staff('admin'))->put(route('products.update', $product), productForm(['barcode' => '', 'sku' => '']))->assertSessionHasNoErrors();

    expect($product->fresh()->barcode)->toBe(Barcode::internalFor($product->id));
});

test('scanner lookups find products by barcode or SKU and explain misreads', function () {
    $product = makeProduct(['barcode' => '4006381333931', 'sku' => 'PEN-01']);

    $this->actingAs(staff())->getJson(route('barcodes.lookup', ['code' => ' 4006381333931 ']))->assertOk()->assertJsonPath('product.id', $product->id);
    $this->actingAs(staff())->getJson(route('barcodes.lookup', ['code' => 'PEN-01']))->assertOk()->assertJsonPath('product.id', $product->id);
    $this->actingAs(staff())->getJson(route('barcodes.lookup', ['code' => '4006381333932']))->assertNotFound()->assertJsonFragment(['message' => 'No product with barcode or SKU “4006381333932”. The check digit is wrong — the code was probably misread; scan again.']);
    $this->actingAs(staff())->getJson(route('barcodes.lookup', ['q' => 'Test']))->assertOk()->assertJsonCount(1, 'products');
});

test('customers can find a product by scanning its barcode in the shop search', function () {
    $product = makeProduct(['name' => 'Fancy Pen', 'barcode' => '4006381333931']);

    $this->actingAs(customer())->get(route('customer.dashboard', ['q' => '4006381333931']))->assertOk()->assertSee('Fancy Pen');
});

test('inactive products are hidden from the shop but kept in the back office', function () {
    $product = makeProduct(['name' => 'Seasonal Mango']);
    $this->actingAs(staff('admin'))->patch(route('products.toggle', $product))->assertSessionHas('success');
    $this->flushSession(); // the admin's flash message names the product

    expect($product->fresh()->is_active)->toBeFalse();
    $this->actingAs(customer())->get(route('customer.dashboard'))->assertDontSee('Seasonal Mango');
    $this->actingAs(customer())->get(route('shop.product', $product))->assertNotFound();
    $this->actingAs(customer())->post(route('cart.add', $product))->assertSessionHas('error');
    $this->actingAs(staff('admin'))->get(route('products.index'))->assertSee('Seasonal Mango');
});

test('barcode images and label sheets are generated', function () {
    $product = makeProduct();

    $this->actingAs(staff())->get(route('barcodes.image', $product))->assertOk()->assertHeader('content-type', 'image/svg+xml');
    $this->actingAs(staff())->get(route('barcodes.labels', ['ids' => [$product->id]]))->assertOk()->assertSee($product->barcode);
    $this->actingAs(staff())->post(route('barcodes.print'), ['items' => [['product_id' => $product->id, 'copies' => 30]], 'show_price' => '1', 'skip' => 5])
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs(staff())->post(route('barcodes.print'), ['items' => [['product_id' => $product->id, 'copies' => 0]]])
        ->assertSessionHas('error');
});
