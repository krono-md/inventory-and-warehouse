<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\OrderReservation;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                'category' => $item->category?->name ?? '—',
                'warehouses' => $item->stockLevels->filter(fn ($sl) => $sl->stock > 0 && $sl->warehouse)->map(fn ($sl) => $sl->warehouse->name)->implode(', '),
                'total_stock' => $item->stockLevels->sum('stock') - $item->stockLevels->sum('reserved_quantity'),
                'unit_cost' => $item->unit_cost,
                'status' => $item->status,
                'stock_breakdown' => $item->stockLevels->filter(fn ($sl) => $sl->warehouse)->map(fn ($sl) => [
                    'stock_level_id' => $sl->id,
                    'warehouse' => $sl->warehouse->name,
                    'on_hand' => $sl->stock - $sl->reserved_quantity,
                    'reserved' => $sl->reserved_quantity,
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:items,sku',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_cost' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'initial_stock' => 'nullable|integer|min:0',
            'reorder_threshold' => 'nullable|integer|min:0',
        ]);

        $item = DB::transaction(function () use ($validated) {
            $item = Item::create([
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'unit_cost' => $validated['unit_cost'],
            ]);

            if (!empty($validated['warehouse_id']) && ($validated['initial_stock'] ?? 0) > 0) {
                $stockLevel = StockLevel::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'stock' => $validated['initial_stock'],
                    'reserved_quantity' => 0,
                    'reorder_threshold' => $validated['reorder_threshold'] ?? 10,
                ]);

                StockMovement::create([
                    'type' => 'inbound',
                    'item_id' => $item->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'quantity' => $validated['initial_stock'],
                    'reference' => 'INIT-' . $item->sku,
                    'notes' => 'Initial stock on creation',
                    'performed_by' => Auth::id(),
                    'created_at' => now(),
                ]);
            }

            return $item;
        });

        return redirect()->route('item-catalog')->with('success', "Item '{$item->sku}' created successfully.");
    }

    public function destroy(Item $item)
    {
        $reserved = OrderReservation::where('item_id', $item->id)
            ->where('status', 'reserved')
            ->exists();

        if ($reserved) {
            return back()->withErrors(['delete' => 'Cannot delete item with active reservations. Cancel reservations first.']);
        }

        $sku = $item->sku;
        $item->delete();

        return redirect()->route('item-catalog')->with('success', "Item '{$sku}' deleted successfully.");
    }
}
