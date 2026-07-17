<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StockLevelController extends Controller
{
    public function index(Request $request)
    {
        $query = StockLevel::with(['item.category', 'warehouse']);

        if ($category = $request->input('category')) {
            $query->whereHas('item', fn ($q) => $q->where('category_id', $category));
        }

        if ($warehouse = $request->input('warehouse')) {
            $query->where('warehouse_id', $warehouse);
        }

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($iq) use ($search) {
                    $iq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                })->orWhereHas('item.category', function ($cq) use ($search) {
                    $cq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                });
            });
        }

        $allFiltered = $query->get();

        if ($status = $request->input('status')) {
            $allFiltered = $allFiltered->filter(fn ($l) => $l->status === $status)->values();
        }

        $page = $request->input('page', 1);
        $perPage = 10;
        $levels = new LengthAwarePaginator(
            $allFiltered->forPage($page, $perPage)->values(),
            $allFiltered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $allLevels = StockLevel::all();

        return view('stock-levels', [
            'stockLevels' => $levels,
            'warehouses' => Warehouse::all(),
            'categories' => Category::all(),
            'filters' => $request->only(['search', 'category', 'warehouse', 'status']),
            'inStockCount' => $allLevels->filter(fn ($l) => $l->status === 'in_stock')->count(),
            'lowStockCount' => $allLevels->filter(fn ($l) => $l->status === 'low_stock')->count(),
            'outOfStockCount' => $allLevels->filter(fn ($l) => $l->status === 'out_of_stock')->count(),
            'activePage' => 'stock-levels',
        ]);
    }

    public function update(Request $request, StockLevel $stockLevel)
    {
        $request->validate(['reorder_threshold' => 'required|integer|min:0']);
        $stockLevel->update(['reorder_threshold' => $request->input('reorder_threshold')]);

        return back()->with('success', 'Reorder threshold updated.');
    }
}
