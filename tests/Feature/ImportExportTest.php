<?php

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Http\UploadedFile;

function csvFile(array $rows): UploadedFile
{
    $lines = array_map(fn ($row) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row)), $rows);

    return UploadedFile::fake()->createWithContent('products.csv', "\xEF\xBB\xBF" . implode("\n", $lines));
}

test('import creates and updates products and records stock changes in the ledger', function () {
    $existing = makeProduct(['sku' => 'TEA-01', 'name' => 'Old tea name', 'price' => 100], stock: 10);

    $this->actingAs(staff('admin'))->post(route('products.import.store'), ['file' => csvFile([
        ['sku', 'barcode', 'name', 'category', 'price', 'cost_price', 'reorder_level', 'active', 'stock'],
        ['tea-01', '', 'Black Tea 400g', 'Food', '250', '190', '12', 'yes', '14'],
        ['', '8901030865275', 'New Soap', 'Beauty & Health', '75', '55', '', 'no', '40'],
    ])])->assertRedirect(route('products.index'))->assertSessionHas('success', 'Import complete: 1 created, 1 updated, 2 stock levels changed.');

    $existing->refresh();
    $soap = Product::where('barcode', '8901030865275')->sole();
    expect($existing->name)->toBe('Black Tea 400g')
        ->and((float) $existing->price)->toBe(250.0)
        ->and(stockOf($existing))->toBe(14)
        ->and(StockMovement::where('product_id', $existing->id)->where('type', 'count')->sole()->change)->toBe(4)
        ->and($soap->is_active)->toBeFalse()
        ->and($soap->reorder_level)->toBe(setting('default_reorder_level'))
        ->and(stockOf($soap))->toBe(40);
    assertLedgerConsistent($existing);
    assertLedgerConsistent($soap);
});

test('a single bad row stops the whole import', function () {
    $this->actingAs(staff('admin'))->post(route('products.import.store'), ['file' => csvFile([
        ['name', 'category', 'price', 'barcode'],
        ['Good Product', 'Food', '10', ''],
        ['Bad Barcode', 'Food', '10', '1234567890123'],
        ['Bad Category', 'Weapons', '-5', ''],
    ])])->assertSessionHas('import_errors');

    $errors = session('import_errors');
    expect(Product::count())->toBe(0)
        ->and($errors)->toHaveCount(2)
        ->and($errors[0])->toContain('Row 3')->toContain('not a valid EAN/UPC')
        ->and($errors[1])->toContain('Row 4')->toContain('category must be one of')->toContain('price must be a number');
});

test('duplicate codes inside the file and unknown columns are reported', function () {
    $this->actingAs(staff('admin'))->post(route('products.import.store'), ['file' => csvFile([
        ['name', 'category', 'price', 'colour'],
        ['A', 'Food', '1', 'red'],
    ])])->assertSessionHas('import_errors', fn ($e) => str_contains($e[0], 'Unknown column(s): colour'));

    $this->actingAs(staff('admin'))->post(route('products.import.store'), ['file' => csvFile([
        ['sku', 'name', 'category', 'price'],
        ['X-1', 'A', 'Food', '1'],
        ['x-1', 'B', 'Food', '1'],
    ])])->assertSessionHas('import_errors', fn ($e) => str_contains($e[0], 'SKU X-1 also appears on row 2'));
});

test('exported CSV can be re-imported unchanged', function () {
    makeProduct(['name' => 'Round Trip, "quoted"', 'barcode' => '4006381333931'], stock: 7);

    $csv = $this->actingAs(staff('admin'))->get(route('products.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('4006381333931');

    $this->actingAs(staff('admin'))->post(route('products.import.store'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])
        ->assertSessionHas('success', 'Import complete: 0 created, 1 updated, 0 stock levels changed.');
    expect(Product::sole()->name)->toBe('Round Trip, "quoted"');
});

test('settings are validated, saved and used across the store', function () {
    $admin = staff('admin');
    $values = collect(Setting::values())->merge(['store_name' => 'Rahman Mart', 'delivery_fee' => 'abc'])->all();

    $this->actingAs($admin)->put(route('admin.settings.update'), $values)->assertSessionHasErrors('delivery_fee');

    $values['delivery_fee'] = 90;
    $this->actingAs($admin)->put(route('admin.settings.update'), $values)->assertSessionHas('success');

    expect(setting('store_name'))->toBe('Rahman Mart')->and(setting('delivery_fee'))->toBe(90.0);
    $this->get('/')->assertSee('Rahman Mart');
    $this->actingAs(staff())->get(route('admin.settings'))->assertRedirect(route('employee.dashboard'));
});
