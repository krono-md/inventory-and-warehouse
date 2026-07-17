<?php

use App\Models\OrderFulfillment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/packing-materials', function () {
    return OrderFulfillment::all();
});
