<?php

use App\Enums\RoleSlug;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\RaffleSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AutomaticBackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RaffleParticipationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'active'])->name('dashboard');
Route::get('/dashboard/inventory-pdf', [DashboardController::class, 'inventoryPdf'])->middleware(['auth', 'active'])->name('dashboard.inventory-pdf');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/backup/automatic', [AutomaticBackupController::class, 'show'])->name('backup.automatic.show');
    Route::post('/backup/automatic', [AutomaticBackupController::class, 'store'])->name('backup.automatic.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('/categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [InventoryMovementController::class, 'index'])->name('inventory.movements.index');
    Route::get('/inventory/{product}/movements/create', [InventoryMovementController::class, 'create'])->name('inventory.movements.create');
    Route::post('/inventory/{product}/movements', [InventoryMovementController::class, 'store'])->name('inventory.movements.store');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [PosController::class, 'search'])->name('pos.products.search');
    Route::get('/pos/raffle-quote', [PosController::class, 'raffleQuote'])->name('pos.raffle.quote');
    Route::put('/pos/cart/{product}', [PosController::class, 'updateCart'])->name('pos.cart.update');
    Route::delete('/pos/cart/{product}', [PosController::class, 'removeCartItem'])->name('pos.cart.destroy');
    Route::delete('/pos/cart', [PosController::class, 'clearCart'])->name('pos.cart.clear');
    Route::post('/pos/sales', [PosController::class, 'store'])->name('pos.sales.store');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/raffle/accept', [RaffleParticipationController::class, 'accept'])->name('sales.raffle.accept');
    Route::post('/sales/{sale}/raffle/decline', [RaffleParticipationController::class, 'decline'])->name('sales.raffle.decline');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
});

Route::prefix('admin')->name('admin.')->middleware([
    'auth',
    'active',
    'role:'.RoleSlug::Administrator->value,
])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password.update');
    Route::patch('raffle-settings', [RaffleSettingController::class, 'update'])->name('raffle-settings.update');
    Route::post('backup', [BackupController::class, 'store'])->name('backup.store');
});

require __DIR__.'/auth.php';
