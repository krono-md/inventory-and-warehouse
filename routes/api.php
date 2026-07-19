<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StockController;
use App\Models\Category;
use App\Models\OrderFulfillment;
use Illuminate\Support\Facades\Route;

Route::get('/packing-materials', function () {
    return OrderFulfillment::all();
});

Route::get('/categories', fn () => Category::all(['id', 'name']));
Route::get('/stock/low', [StockController::class, 'lowStock']);
Route::get('/stock/{sku}', [StockController::class, 'show']);
Route::get('/items', [StockController::class, 'items']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/reserve', [OrderController::class, 'reserve']);
    Route::post('/orders/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/cancel', [OrderController::class, 'cancel']);

    Route::post('/items', [StockController::class, 'store']);
    Route::put('/items/{item}', [StockController::class, 'update']);
    Route::delete('/items/{item}', [StockController::class, 'destroy']);
});
