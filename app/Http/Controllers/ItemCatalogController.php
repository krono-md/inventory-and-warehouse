<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ItemCatalogController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::all();
        $warehouses = Warehouse::all();

        $query = Item::with(['category', 'stockLevels.warehouse']);

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('category', function ($cq) use ($search) {
                      $cq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                  });
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category_id', $category);
        }

        if ($warehouse = $request->input('warehouse')) {
            $query->whereHas('stockLevels', fn ($q) => $q->where('warehouse_id', $warehouse));
        }

        if ($status = $request->input('status')) {
            match ($status) {
                'Out of Stock' => $query->outOfStock(),
                'Low Stock' => $query->lowStock(),
                'In Stock' => $query->inStock(),
                default => null,
            };
        }

        $items = $query->get();

        $allLevels = StockLevel::all();

        $items = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category->name,
                'warehouses' => $item->stockLevels->filter(fn ($sl) => $sl->stock > 0 && $sl->warehouse)->map(fn ($sl) => $sl->warehouse->name)->implode(', '),
                'total_stock' => $item->stockLevels->sum('stock'),
                'unit_cost' => $item->unit_cost,
                'status' => $item->status,
                'stock_breakdown' => $item->stockLevels->filter(fn ($sl) => $sl->warehouse)->map(fn ($sl) => [
                    'stock_level_id' => $sl->id,
                    'warehouse' => $sl->warehouse->name,
                    'on_hand' => $sl->stock,
                    'reorder_threshold' => $sl->reorder_threshold,
                    'status' => $sl->status_label,
                ])->values()->toArray(),
            ];
        });

        $page = $request->input('page', 1);
        $perPage = 10;
        $paginated = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('item-catalog', [
            'items' => $paginated,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'inStockCount' => $allLevels->filter(fn ($l) => $l->status === 'in_stock')->count(),
            'lowStockCount' => $allLevels->filter(fn ($l) => $l->status === 'low_stock')->count(),
            'outOfStockCount' => $allLevels->filter(fn ($l) => $l->status === 'out_of_stock')->count(),
            'activePage' => 'item-catalog',
        ]);
    }
}
