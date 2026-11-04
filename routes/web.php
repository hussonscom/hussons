<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\RegisteredOrganizationController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReturnController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrganizationContextController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PlatformAdminController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::match(['get', 'post'], '/locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['az', 'ru', 'en'])
    ->name('locale');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredOrganizationController::class, 'store'])->name('register.store');
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])
        ->middleware('throttle:6,1')
        ->name('invitations.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitations.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('logout');

Route::get('/platform', [PlatformAdminController::class, 'index'])
    ->middleware(['auth', 'active'])
    ->name('platform.index');

Route::prefix('app')->middleware(['auth', 'active', 'organization'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::patch('/context', [OrganizationContextController::class, 'update'])
        ->name('organization.context');

    Route::get('/products', [CatalogController::class, 'index'])
        ->middleware('organization.role:owner,admin,branch_manager,warehouse,cashier,pharmacist,kitchen,auditor,viewer')
        ->name('catalog.index');

    Route::middleware('organization.role:owner,admin,branch_manager')->group(function (): void {
        Route::get('/products/create', [CatalogController::class, 'create'])->name('catalog.create');
        Route::post('/products', [CatalogController::class, 'store'])->name('catalog.store');
        Route::get('/products/{product}/edit', [CatalogController::class, 'edit'])->name('catalog.edit');
        Route::match(['put', 'patch'], '/products/{product}', [CatalogController::class, 'update'])->name('catalog.update');
        Route::delete('/products/{product}', [CatalogController::class, 'destroy'])->name('catalog.destroy');
    });

    Route::middleware('organization.role:owner,admin,branch_manager,warehouse')->group(function (): void {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/receive', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory/receive', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('/inventory/serials', [InventoryController::class, 'serials'])->name('inventory.serials');
        Route::get('/inventory/purchases/{purchase}', [InventoryController::class, 'show'])
            ->name('inventory.purchase');
        Route::patch('/inventory/returns/{unit}', [InventoryReturnController::class, 'update'])
            ->name('inventory.returns.update');
    });

    Route::middleware('organization.role:owner,admin')->group(function (): void {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::post('/team/invitations', [TeamController::class, 'invite'])->name('team.invitations.store');
        Route::patch('/team/{membership}', [TeamController::class, 'update'])->name('team.update');
    });

    Route::get('/directory', [DirectoryController::class, 'index'])
        ->middleware('organization.role:owner,admin,branch_manager,warehouse,cashier,pharmacist')
        ->name('directory.index');

    Route::middleware('organization.role:owner,admin,branch_manager')->group(function (): void {
        Route::post('/directory/brands', [DirectoryController::class, 'storeBrand'])->name('directory.brands.store');
        Route::patch('/directory/brands/{brand}', [DirectoryController::class, 'updateBrand'])->name('directory.brands.update');
        Route::delete('/directory/brands/{brand}', [DirectoryController::class, 'archiveBrand'])->name('directory.brands.archive');
    });

    Route::middleware('organization.role:owner,admin,branch_manager,warehouse')->group(function (): void {
        Route::post('/directory/suppliers', [DirectoryController::class, 'storeSupplier'])->name('directory.suppliers.store');
        Route::patch('/directory/suppliers/{supplier}', [DirectoryController::class, 'updateSupplier'])->name('directory.suppliers.update');
        Route::delete('/directory/suppliers/{supplier}', [DirectoryController::class, 'archiveSupplier'])->name('directory.suppliers.archive');
    });

    Route::middleware('organization.role:owner,admin,branch_manager,cashier,pharmacist')->group(function (): void {
        Route::post('/directory/customers', [DirectoryController::class, 'storeCustomer'])->name('directory.customers.store');
        Route::patch('/directory/customers/{customer}', [DirectoryController::class, 'updateCustomer'])->name('directory.customers.update');
        Route::delete('/directory/customers/{customer}', [DirectoryController::class, 'archiveCustomer'])->name('directory.customers.archive');
    });

    Route::middleware('organization.role:owner,admin,branch_manager,cashier,pharmacist')->group(function (): void {
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/create', [SalesController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SalesController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}', [SalesController::class, 'show'])->name('sales.show');
    });

    Route::middleware('organization.role:owner,admin,branch_manager')->group(function (): void {
        Route::post('/sales/{sale}/refund', [SalesController::class, 'refund'])->name('sales.refund');
    });

    Route::get('/pharmacy', [PharmacyController::class, 'index'])
        ->middleware([
            'organization.module:pharmacy',
            'organization.role:owner,admin,branch_manager,pharmacist,auditor',
        ])
        ->name('pharmacy.index');

    Route::middleware([
        'organization.module:restaurant',
        'organization.role:owner,admin,branch_manager,cashier,kitchen',
    ])->group(function (): void {
        Route::get('/restaurant', [RestaurantController::class, 'index'])->name('restaurant.index');
        Route::post('/restaurant/tables', [RestaurantController::class, 'storeTable'])
            ->name('restaurant.tables.store');
        Route::post('/restaurant/orders', [RestaurantController::class, 'storeOrder'])
            ->name('restaurant.orders.store');
        Route::patch('/restaurant/orders/{order}/status', [RestaurantController::class, 'updateStatus'])
            ->name('restaurant.orders.status');
        Route::post('/restaurant/orders/{order}/pay', [RestaurantController::class, 'pay'])
            ->name('restaurant.orders.pay');
        Route::patch('/restaurant/items/{item}/status', [RestaurantController::class, 'updateItemStatus'])
            ->name('restaurant.items.status');
    });
});
