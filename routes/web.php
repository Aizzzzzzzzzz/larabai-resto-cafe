<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Cashier;
use App\Http\Controllers\Customer;
use App\Http\Controllers\Inventory;
use App\Http\Controllers\Kitchen;
use App\Http\Controllers\Public;
use Illuminate\Support\Facades\Route;

/*
| Brew & Bite Café — routes, one group per module (development-plan.md §4).
| Every write is a plain HTML form (POST + @csrf, PATCH/PUT/DELETE via @method).
|
| UI scaffold: no auth yet. Phase 0 adds `auth` + `role:` middleware to the staff groups
| (cashier → cashier + admin, kitchen → kitchen + admin, inventory → inventory + admin, admin → admin).
*/

// ---- Public ----
Route::get('/', [Public\HomeController::class, 'index'])->name('home');
Route::get('/navigator', [Public\NavigatorController::class, 'index'])->name('navigator');
Route::get('/navigator/components', [Public\ComponentGalleryController::class, 'staff'])->name('navigator.components.staff');
Route::get('/navigator/components/customer', [Public\ComponentGalleryController::class, 'customer'])->name('navigator.components.customer');

// ---- Staff login ----
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// ---- Customer: QR codes (signed links, so the table number can't be edited) ----
Route::get('/t/{table}', [Customer\SessionController::class, 'table'])
    ->middleware('signed')->whereNumber('table')->name('customer.session.table');
Route::get('/counter', [Customer\SessionController::class, 'counter'])
    ->middleware('signed')->name('customer.session.counter');

// ---- Customer: ordering ----
Route::prefix('order')->name('customer.')->group(function () {
    Route::get('/menu', [Customer\MenuController::class, 'index'])->name('menu');

    Route::get('/cart', [Customer\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [Customer\CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{line}', [Customer\CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{line}', [Customer\CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/checkout', [Customer\CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [Customer\CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/mine', [Customer\OrderController::class, 'index'])->name('orders.index');

    // {order} = the order's public token
    Route::get('/{order}/pay', [Customer\PaymentController::class, 'create'])->name('pay.create');
    Route::post('/{order}/pay', [Customer\PaymentController::class, 'store'])->name('pay.store');
    Route::get('/{order}/done', [Customer\OrderController::class, 'success'])->name('orders.success');
    Route::get('/{order}/status', [Customer\OrderController::class, 'status'])->name('orders.status');
    Route::post('/{order}/cancel', [Customer\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/{order}', [Customer\OrderController::class, 'show'])->name('orders.show');
});

// ---- Cashier ----
Route::prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/pos', [Cashier\PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [Cashier\PosController::class, 'store'])->name('pos.store');

    Route::get('/orders', [Cashier\OrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}/complete', [Cashier\OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/cancel', [Cashier\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/void', [Cashier\OrderController::class, 'void'])->name('orders.void');

    Route::get('/orders/{order}/payment', [Cashier\PaymentController::class, 'create'])->name('payments.create');
    Route::post('/orders/{order}/payment', [Cashier\PaymentController::class, 'store'])->name('payments.store');

    Route::get('/orders/{order}/receipt', [Cashier\ReceiptController::class, 'show'])->name('receipt');
});

// ---- Kitchen ----
Route::prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', [Kitchen\DisplayController::class, 'index'])->name('display');
    Route::get('/board', [Kitchen\DisplayController::class, 'board'])->name('board');
    Route::patch('/orders/{order}/status', [Kitchen\OrderStatusController::class, 'update'])->name('orders.status');
    Route::patch('/menu-items/{menuItem}/availability', [Kitchen\AvailabilityController::class, 'update'])->name('availability.update');
});

// ---- Inventory ----
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/ingredients', [Inventory\IngredientController::class, 'index'])->name('ingredients.index');
    Route::post('/ingredients', [Inventory\IngredientController::class, 'store'])->name('ingredients.store');
    Route::put('/ingredients/{ingredient}', [Inventory\IngredientController::class, 'update'])->name('ingredients.update');
    Route::post('/ingredients/{ingredient}/restock', [Inventory\RestockController::class, 'store'])->name('ingredients.restock');
    Route::get('/ingredients/{ingredient}/history', [Inventory\MovementController::class, 'index'])->name('ingredients.history');
});

// ---- Admin ----
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('menu-items', Admin\MenuItemController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories', Admin\CategoryController::class)->only(['store', 'update', 'destroy']);

    Route::resource('users', Admin\UserController::class)->only(['index', 'store', 'update']);
    Route::patch('/users/{user}/password', [Admin\UserController::class, 'password'])->name('users.password');
    Route::patch('/users/{user}/active', [Admin\UserController::class, 'active'])->name('users.active');

    Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [Admin\AuditLogController::class, 'export'])->name('audit-logs.export');

    Route::get('/demo-reset', [Admin\DemoResetController::class, 'show'])->name('demo-reset.show');
    Route::post('/demo-reset', [Admin\DemoResetController::class, 'store'])->name('demo-reset.store');

    Route::get('/tables/qr', [Admin\TableQrController::class, 'index'])->name('tables.qr');
});
