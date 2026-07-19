<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OrderReservation;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function show(string $sku): JsonResponse
    {
        $item = Item::where('sku', $sku)->first();

        if (!$item) {
            return response()->json(['error' => 'Item not found.'], 404);
        }

        $stockLevels = StockLevel::with('warehouse')
            ->where('item_id', $item->id)
            ->get()
            ->map(fn ($sl) => [
                'warehouse_id' => $sl->warehouse_id,
                'warehouse' => $sl->warehouse?->name,
                'quantity' => $sl->stock,
                'reserved' => $sl->reserved_quantity,
                'available' => $sl->available_quantity,
                'reorder_threshold' => $sl->reorder_threshold,
                'status' => $sl->status,
            ]);

        $totalAvailable = $item->total_available;

        $hasLow = $totalAvailable > 0 && $stockLevels->contains(fn ($sl) => $sl['status'] === 'low_stock');

        return response()->json([
            'sku' => $item->sku,
            'name' => $item->name,
            'status' => $totalAvailable <= 0 ? 'out_of_stock' : ($hasLow ? 'low_stock' : 'in_stock'),
            'total_stock' => $item->total_stock,
            'total_available' => $totalAvailable,
            'stock_levels' => $stockLevels,
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $levels = StockLevel::with(['item', 'warehouse'])
            ->whereColumn('stock', '>', 'reserved_quantity')
            ->whereRaw('stock - reserved_quantity <= reorder_threshold')
            ->where('reorder_threshold', '>', 0)
            ->get()
            ->map(fn ($sl) => [
                'sku' => $sl->item?->sku,
                'item' => $sl->item?->name,
                'warehouse' => $sl->warehouse?->name,
                'quantity' => $sl->stock,
                'reserved' => $sl->reserved_quantity,
                'available' => $sl->available_quantity,
                'reorder_threshold' => $sl->reorder_threshold,
            ]);

        return response()->json(['data' => $levels]);
    }

    public function items(Request $request): JsonResponse
    {
        $query = Item::with(['category', 'stockLevels.warehouse']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $items = $query->paginate($request->input('per_page', 50));

        $items->getCollection()->transform(function ($item) {
            $totalAvailable = $item->total_available;
            $hasLow = $totalAvailable > 0 && $item->stockLevels->contains(fn ($sl) => $sl->status === 'low_stock');

            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category?->name,
                'unit_cost' => $item->unit_cost,
                'status' => $totalAvailable <= 0 ? 'out_of_stock' : ($hasLow ? 'low_stock' : 'in_stock'),
                'total_stock' => $item->total_stock,
                'total_available' => $totalAvailable,
                'stock_levels' => $item->stockLevels->map(fn ($sl) => [
                    'warehouse' => $sl->warehouse?->name,
                    'quantity' => $sl->stock,
                    'reserved' => $sl->reserved_quantity,
                    'available' => $sl->available_quantity,
                    'reorder_threshold' => $sl->reorder_threshold,
                ]),
            ];
        });

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
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

        $item = DB::transaction(function () use ($validated, $request) {
            $item = Item::create([
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'unit_cost' => $validated['unit_cost'],
            ]);

            if (!empty($validated['warehouse_id']) && ($validated['initial_stock'] ?? 0) > 0) {
                StockLevel::create([
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
                    'notes' => 'Initial stock on creation via API',
                    'performed_by' => $request->user()?->id,
                    'created_at' => now(),
                ]);
            }

            return $item;
        });

        return response()->json([
            'message' => 'Item created successfully.',
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
            ],
        ], 201);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'sometimes|string|max:50|unique:items,sku,' . $item->id,
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'unit_cost' => 'sometimes|numeric|min:0',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully.',
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
            ],
        ]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $reserved = OrderReservation::where('item_id', $item->id)
            ->where('status', 'reserved')
            ->exists();

        if ($reserved) {
            return response()->json(['error' => 'Cannot delete item with active reservations.'], 409);
        }

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully.']);
    }
}
