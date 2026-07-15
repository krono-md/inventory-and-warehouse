<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::with(['item', 'warehouse', 'performer'])->orderByDesc('created_at');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($warehouse = $request->input('warehouse')) {
            $query->where('warehouse_id', $warehouse);
        }

        if ($reference = $request->input('reference')) {
            $query->where('reference', $reference);
        }

        if ($dateRange = $request->input('date_range')) {
            match ($dateRange) {
                'today' => $query->whereDate('created_at', today()),
                'this_week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'this_month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                default => null,
            };
        }

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(reference) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('item', function ($iq) use ($search) {
                      $iq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                         ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$search}%"]);
                  });
            });
        }

        $movements = $query->paginate(10)->appends($request->query());

        $totals = [
            'inbound' => StockMovement::where('type', 'inbound')->sum('quantity'),
            'outbound' => StockMovement::where('type', 'outbound')->sum('quantity'),
            'transfer' => StockMovement::where('type', 'transfer')->sum('quantity'),
            'adjustment' => StockMovement::where('type', 'adjustment')->sum('quantity'),
        ];
        $totals['net'] = $totals['inbound'] - $totals['outbound'] + $totals['adjustment'];

        $references = StockMovement::pluck('reference')->unique()->filter()->values();

        return view('stock-movement', [
            'movements' => $movements,
            'warehouses' => Warehouse::all(),
            'totals' => $totals,
            'references' => $references,
            'activePage' => 'stock-movement',
        ]);
    }
}
