<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;

Route::get('/', fn () => view('signin'))->name('signin');
Route::get('/contact-us', fn () => view('contactus'))->name('contactus');
Route::get('/index', fn () => view('index', [
    'totalItems' => 0,
    'totalStockUnits' => 0,
    'totalInboundToday' => 0,
    'statusCards' => collect(),
    'trendLabels' => [],
    'inboundData' => [],
    'outboundData' => [],
    'warehouseDistribution' => collect(),
    'recentMovements' => collect(),
]))->name('index');
Route::get('/item-catalog', fn () => view('item-catalog', [
    'items' => collect(),
    'warehouses' => collect(),
]))->name('item-catalog');
Route::get('/stock-adjustments', fn () => view('stock-adjustments', [
    'errors' => new ViewErrorBag,
    'warehouses' => [],
    'items' => [],
]))->name('stock-adjustments');
Route::get('/stock-levels', fn () => view('stock-levels', [
    'stockLevels' => collect(),
    'warehouses' => collect(),
    'categories' => collect(),
    'filters' => [],
    'inStockCount' => 0,
    'lowStockCount' => 0,
    'outOfStockCount' => 0,
]))->name('stock-levels');
Route::get('/stock-movement', fn () => view('stock-movement', [
    'movements' => collect(),
    'warehouses' => collect(),
    'totals' => ['inbound' => 0, 'outbound' => 0, 'transfer' => 0],
]))->name('stock-movement');
Route::get('/warehouse', fn () => view('warehouse', [
    'errors' => new ViewErrorBag,
    'warehouses' => [],
]))->name('warehouse');
