<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemCatalogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockLevelController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReceivingController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Auth (public)
Route::get('/', [AuthController::class, 'showLogin'])->name('signin');
Route::get('/signin', [AuthController::class, 'showLogin'])->name('signin.get');
Route::post('/signin', [AuthController::class, 'login'])->name('signin.authenticate');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/contact-us', fn () => view('contactus'))->name('contactus');

// Protected routes
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/index', [DashboardController::class, 'index'])->name('index');
    Route::get('/index/trend-data', [DashboardController::class, 'trendData'])->name('index.trend-data');

    // Item Catalog
    Route::get('/item-catalog', [ItemCatalogController::class, 'index'])->name('item-catalog');
    Route::post('/item-catalog', [ItemCatalogController::class, 'store'])->name('item-catalog.store');
    Route::delete('/item-catalog/{item}', [ItemCatalogController::class, 'destroy'])->name('item-catalog.destroy');

    // Stock Movement
    Route::get('/stock-movement', [StockMovementController::class, 'index'])->name('stock-movement');

    // Stock Levels table update
    Route::patch('/stock-levels/{stockLevel}', [StockLevelController::class, 'update'])->name('stock-levels.update');

    // Stock Adjustments
    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    Route::patch('/stock-adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
    Route::patch('/stock-adjustments/{adjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');
    Route::patch('/stock-adjustments/{adjustment}/cancel', [StockAdjustmentController::class, 'cancel'])->name('stock-adjustments.cancel');

    // Warehouse
    Route::get('/warehouse', [WarehouseController::class, 'index'])->name('warehouse');
    Route::post('/warehouse', [WarehouseController::class, 'store'])->name('warehouse.store');
    Route::patch('/warehouse/{warehouse}', [WarehouseController::class, 'update'])->name('warehouse.update');
    Route::delete('/warehouse/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::patch('/notifications/{notification}/acknowledge', [NotificationController::class, 'acknowledge'])->name('notifications.acknowledge');
    Route::patch('/notifications/{notification}/resolve', [NotificationController::class, 'resolve'])->name('notifications.resolve');

    // Stock Receiving
    Route::get('/stock-receiving', [StockReceivingController::class, 'index'])->name('stock-receiving');
    Route::post('/stock-receiving/{delivery}/approve', [StockReceivingController::class, 'approve'])->name('stock-receiving.approve');
    Route::post('/stock-receiving/{delivery}/reject', [StockReceivingController::class, 'reject'])->name('stock-receiving.reject');

    // Stock Transfers
    Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers');
    Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
    Route::patch('/stock-transfers/{transfer}/approve', [StockTransferController::class, 'approve'])->name('stock-transfers.approve');
    Route::patch('/stock-transfers/{transfer}/reject', [StockTransferController::class, 'reject'])->name('stock-transfers.reject');
    Route::patch('/stock-transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
});
