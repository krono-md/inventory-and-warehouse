<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReceiving;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockReceivingController extends Controller
{
    public function index(Request $request)
    {
        // Fetch deliveries from procurement database with status 'pending' or 'in transit'
        $query = Delivery::whereIn('status', ['pending', 'in transit'])
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(shipment_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(items) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $deliveries = $query->paginate(10)->appends($request->query());

        // Get processed shipment numbers to mark which deliveries have been processed
        $processedShipments = StockReceiving::pluck('shipment_number')->toArray();

        // Statistics
        $pendingCount = Delivery::whereIn('status', ['pending', 'in transit'])->count();
        $receivedTodayCount = StockReceiving::whereDate('processed_at', today())
            ->where('status', 'approved')
            ->count();
        $rejectedCount = StockReceiving::where('status', 'rejected')->count();

        $warehouses = Warehouse::where('status', 'active')->whereNull('deleted_at')->get();
        $categories = Category::all();
        $items = Item::all();

        return view('stock-receiving', [
            'deliveries' => $deliveries,
            'processedShipments' => $processedShipments,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'items' => $items,
            'pendingCount' => $pendingCount,
            'receivedTodayCount' => $receivedTodayCount,
            'rejectedCount' => $rejectedCount,
            'filters' => $request->only(['search', 'status']),
            'activePage' => 'stock-receiving',
        ]);
    }

    public function approve(Request $request, $deliveryId)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'nullable|exists:items,id',
            'category_id' => 'required_without:item_id|exists:categories,id',
            'item_name' => 'required_without:item_id|string',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $delivery = Delivery::findOrFail($deliveryId);

        $result = $this->executeApproval($delivery, $validated);

        if ($result === true) {
            return back()->with('success', 'Delivery approved and stock updated.');
        }

        return back()->with('error', $result);
    }

    private function executeApproval(Delivery $delivery, array $validated): true|string
    {
        return DB::transaction(function () use ($delivery, $validated) {
            // Check if already processed INSIDE transaction to prevent race condition
            if (StockReceiving::where('shipment_number', $delivery->shipment_number)->exists()) {
                return 'This delivery has already been processed.';
            }
            // Determine if creating new item or using existing
            if (isset($validated['item_id'])) {
                // Existing item
                $item = Item::find($validated['item_id']);
            } else {
                // Create new item
                $item = Item::create([
                    'name' => $validated['item_name'],
                    'category_id' => $validated['category_id'],
                    'unit_cost' => $validated['unit_cost'] ?? 0,
                ]);
            }

            // Get or create stock level for this item and warehouse
            $stockLevel = StockLevel::firstOrCreate(
                [
                    'item_id' => $item->id,
                    'warehouse_id' => $validated['warehouse_id'],
                ],
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'reorder_threshold' => 10,
                ]
            );

            $stockLevel->notification_source = 'receiving';
            $stockLevel->increment('quantity_on_hand', $delivery->qty);

            // Update warehouse activity
            Warehouse::where('id', $validated['warehouse_id'])
                ->update(['last_activity_at' => now()]);

            // Create stock movement record
            StockMovement::create([
                'type' => 'inbound',
                'item_id' => $item->id,
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $delivery->qty,
                'reference' => $delivery->shipment_number,
                'notes' => "Stock received from procurement - Shipment: {$delivery->shipment_number}",
                'performed_by' => Auth::id(),
                'created_at' => now(),
            ]);

            // Record the receiving
            StockReceiving::create([
                'shipment_number' => $delivery->shipment_number,
                'item_id' => $item->id,
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $delivery->qty,
                'status' => 'approved',
                'processed_by' => Auth::id(),
                'remarks' => $delivery->remarks,
                'processed_at' => now(),
            ]);

            return true;
        });
    }

    public function reject(Request $request, $deliveryId)
    {
        $validated = $request->validate([
            'reject_reason' => 'required|string',
        ]);

        $delivery = Delivery::findOrFail($deliveryId);

        // Check if already processed
        if (StockReceiving::where('shipment_number', $delivery->shipment_number)->exists()) {
            return back()->with('error', 'This delivery has already been processed.');
        }

        // Record the rejection
        StockReceiving::create([
            'shipment_number' => $delivery->shipment_number,
            'item_id' => 1, // Placeholder - rejected items don't create item records
            'warehouse_id' => 1, // Placeholder
            'quantity' => $delivery->qty,
            'status' => 'rejected',
            'processed_by' => Auth::id(),
            'remarks' => $validated['reject_reason'],
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Delivery rejected.');
    }
}
