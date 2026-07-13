<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StockReceivingController extends Controller
{
    public function index()
    {
        return view('stock-receiving', [
            'pendingCount' => 0,
            'receivedTodayCount' => 0,
            'discrepancyCount' => 0,
            'receivings' => collect(),
            'activePage' => 'stock-receiving',
        ]);
    }
}
