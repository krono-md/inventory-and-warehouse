<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OrderReservation;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_reference' => 'required|string|max:100',
            'source' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|max:50',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $reservations = [];

        $result = DB::transaction(function () use ($validated, &$reservations, $request) {
            foreach ($validated['items'] as $line) {
                $item = Item::where('sku', $line['sku'])->lockForUpdate()->first();

                if (!$item) {
                    return ['error' => "Item not found: {$line['sku']}", 'line' => $line];
                }

                // Prevent double-reservation if e-commerce retries the same request
                $existing = OrderReservation::where('order_reference', $validated['order_reference'])
                    ->where('item_id', $item->id)
                    ->where('warehouse_id', $line['warehouse_id'])
                    ->where('status', 'reserved')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return ['error' => "SKU {$line['sku']} in warehouse {$line['warehouse_id']} is already reserved for order {$validated['order_reference']}.", 'line' => $line];
                }

                $stockLevel = StockLevel::where('item_id', $item->id)
                    ->where('warehouse_id', $line['warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stockLevel) {
                    return ['error' => "No stock for SKU {$line['sku']} in warehouse {$line['warehouse_id']}", 'line' => $line];
                }

                $available = $stockLevel->stock - $stockLevel->reserved_quantity;

                if ($available < $line['quantity']) {
                    return ['error' => "Insufficient available stock for {$line['sku']}. Requested {$line['quantity']}, available {$available}.", 'line' => $line];
                }

                $stockLevel->increment('reserved_quantity', $line['quantity']);

                $reservation = OrderReservation::create([
                    'order_reference' => $validated['order_reference'],
                    'source' => $validated['source'] ?? 'api',
                    'item_id' => $item->id,
                    'warehouse_id' => $line['warehouse_id'],
                    'quantity' => $line['quantity'],
                    'status' => 'reserved',
                    'reserved_at' => now(),
                ]);

                $reservations[] = $reservation;

                StockMovement::create([
                    'type' => 'reservation',
                    'item_id' => $item->id,
                    'warehouse_id' => $line['warehouse_id'],
                    'quantity' => -$line['quantity'],
                    'reference' => $validated['order_reference'],
                    'notes' => "Reserved for order {$validated['order_reference']}",
                    'performed_by' => $request->user()?->id,
                    'created_at' => now(),
                ]);
            }

            return ['success' => true, 'reservations' => $reservations];
        });

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Stock reserved successfully.',
            'order_reference' => $validated['order_reference'],
            'reservations' => $reservations,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_reference' => 'required|string|max:100',
        ]);

        $reservations = OrderReservation::where('order_reference', $validated['order_reference'])
            ->where('status', 'reserved')
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json(['error' => 'No active reservations found for this order.'], 404);
        }

        $processed = 0;

        DB::transaction(function () use ($reservations, $validated, &$processed) {
            foreach ($reservations as $reservation) {
                $reservation = OrderReservation::lockForUpdate()->find($reservation->id);

                if ($reservation->status !== 'reserved') {
                    continue;
                }

                $stockLevel = StockLevel::where('item_id', $reservation->item_id)
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stockLevel) {
                    $stockLevel->decrement('stock', $reservation->quantity);
                    $stockLevel->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);

                StockMovement::create([
                    'type' => 'outbound',
                    'item_id' => $reservation->item_id,
                    'warehouse_id' => $reservation->warehouse_id,
                    'quantity' => -$reservation->quantity,
                    'reference' => $validated['order_reference'],
                    'notes' => "Order confirmed - stock deducted",
                    'created_at' => now(),
                ]);

                $processed++;
            }
        });

        if ($processed === 0) {
            return response()->json(['error' => 'All reservations for this order have already been processed.'], 409);
        }

        return response()->json(['message' => 'Order confirmed and stock deducted.']);
    }

    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_reference' => 'required|string|max:100',
        ]);

        $reservations = OrderReservation::where('order_reference', $validated['order_reference'])
            ->where('status', 'reserved')
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json(['error' => 'No active reservations found for this order.'], 404);
        }

        $processed = 0;

        DB::transaction(function () use ($reservations, $validated, &$processed) {
            foreach ($reservations as $reservation) {
                $reservation = OrderReservation::lockForUpdate()->find($reservation->id);

                if ($reservation->status !== 'reserved') {
                    continue;
                }

                $stockLevel = StockLevel::where('item_id', $reservation->item_id)
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stockLevel) {
                    $stockLevel->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                StockMovement::create([
                    'type' => 'reservation_release',
                    'item_id' => $reservation->item_id,
                    'warehouse_id' => $reservation->warehouse_id,
                    'quantity' => $reservation->quantity,
                    'reference' => $validated['order_reference'],
                    'notes' => "Reservation cancelled - stock released",
                    'created_at' => now(),
                ]);

                $processed++;
            }
        });

        if ($processed === 0) {
            return response()->json(['error' => 'All reservations for this order have already been processed.'], 409);
        }

        return response()->json(['message' => 'Reservation cancelled and stock released.']);
    }
}
