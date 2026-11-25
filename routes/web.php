<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Manage\OrderController as ManageOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

// Sends every signed-in user to the dashboard that matches their role.
Route::get('/dashboard', fn () => redirect()->route(auth()->user()->homeRoute()))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Customers can download their own invoices; staff can download any.
    Route::get('invoice/{order}', [InvoiceController::class, 'show'])->name('invoice.show');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Customer: shop, cart, orders
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:customer'])->group(function () {
    Route::get('customer/dashboard', [CustomerController::class, 'index'])->name('customer.dashboard');
    Route::get('shop/products/{product}', [CustomerController::class, 'show'])->name('shop.product');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/add-to-cart/{product}', [CartController::class, 'addToCart'])->name('cart.add');
    Route::post('/cart/update/{item}', [CartController::class, 'updateQuantity'])->name('cart.update');
    Route::post('/cart/remove/{item}', [CartController::class, 'removeProduct'])->name('cart.remove');
    Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.applyCoupon');
    Route::post('/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('cart.removeCoupon');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->middleware('throttle:10,1')->name('cart.checkout');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
});

/*
|--------------------------------------------------------------------------
| Staff (admins and employees): till, scanning, orders, receiving stock
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin,employee'])->group(function () {
    Route::prefix('manage')->name('manage.')->group(function () {
        Route::get('orders', [ManageOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [ManageOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [ManageOrderController::class, 'updateStatus'])->name('orders.status');
    });

    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos/quote', [PosController::class, 'quote'])->middleware('throttle:240,1')->name('pos.quote');
    Route::post('pos/sales', [PosController::class, 'store'])->name('pos.store');
    Route::get('pos/receipt/{order}', [PosController::class, 'receipt'])->name('pos.receipt');

    Route::get('scan', [BarcodeController::class, 'scan'])->name('barcodes.scan');
    Route::get('barcodes/lookup', [BarcodeController::class, 'lookup'])->middleware('throttle:240,1')->name('barcodes.lookup');
    Route::get('barcodes/{product}.svg', [BarcodeController::class, 'image'])->withTrashed()->name('barcodes.image');
    Route::get('barcodes/labels', [BarcodeController::class, 'labels'])->name('barcodes.labels');
    Route::post('barcodes/labels', [BarcodeController::class, 'printLabels'])->name('barcodes.print');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('inventory/{product}/receive', [InventoryController::class, 'receive'])->name('inventory.receive');
});

Route::middleware(['auth', 'verified', 'role:employee'])->group(function () {
    Route::get('employee/dashboard', [EmployeeController::class, 'index'])->name('employee.dashboard');
    Route::redirect('employee/stock', '/inventory')->name('employee.stock');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/customers', [AdminController::class, 'customers'])->name('admin.customers');

    // Products (import/export routes come before the resource so "import" is not read as an id)
    Route::get('products/import', [ProductImportController::class, 'show'])->name('products.import');
    Route::post('products/import', [ProductImportController::class, 'import'])->name('products.import.store');
    Route::get('products/export', [ProductImportController::class, 'export'])->name('products.export');
    Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
    Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
    Route::resource('products', ProductController::class)->except('show');

    // Stock adjustments that change the books
    Route::post('inventory/{product}/write-off', [InventoryController::class, 'writeOff'])->name('inventory.write-off');
    Route::post('inventory/{product}/count', [InventoryController::class, 'count'])->name('inventory.count');
    Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
    Route::get('admin/stock-report', [InventoryController::class, 'report'])->name('admin.stock.report');
    Route::redirect('admin/stocks', '/inventory')->name('admin.stocks');
    Route::redirect('admin/manage-stocks', '/inventory')->name('admin.manage.stocks');

    // Staff accounts
    Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Coupons
    Route::get('admin/coupons', [CouponController::class, 'index'])->name('admin.coupons');
    Route::get('admin/coupons/create', [CouponController::class, 'create'])->name('admin.coupons.create');
    Route::post('admin/coupons', [CouponController::class, 'store'])->name('admin.coupons.store');
    Route::patch('admin/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('admin.coupons.toggle');
    Route::delete('admin/coupons/{coupon}', [CouponController::class, 'destroy'])->name('admin.coupons.destroy');

    // Settings
    Route::get('admin/settings', [SettingsController::class, 'edit'])->name('admin.settings');
    Route::put('admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    // Sales and reports (the two legacy sales URLs now land on the reports page)
    Route::get('admin/sales-reports', [AdminController::class, 'salesReports'])->name('admin.sales-reports');
    Route::redirect('admin/sales-dashboard', '/admin/sales-reports')->name('admin.sales-dashboard');
    Route::redirect('admin/sales-overview', '/admin/sales-reports')->name('admin.sales.overview');
    Route::get('top-products', [ProductController::class, 'topProducts'])->name('products.top');
    Route::get('download-seasonal-report', [ProductController::class, 'downloadSeasonalReport'])->name('downloadSeasonalReport');
    Route::get('monthly-report', [ProductController::class, 'downloadMonthlyReport'])->name('monthly.report.pdf');
});
