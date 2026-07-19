<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Procurement;
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
        $query = Procurement::whereIn('status', ['pending', 'in transit'])
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
        // For procurement
        $processedShipments = StockReceiving::pluck('shipment_number')->toArray();

        // For kpi cards
        $pendingCount = Procurement::whereIn('status', ['pending', 'in transit'])->count();
        $receivedTodayCount = StockReceiving::whereDate('processed_at', today())
            ->where('status', 'approved')
            ->count();
        $rejectedCount = StockReceiving::where('status', 'rejected')->count();

        $warehouses = Warehouse::where('status', 'active')->whereNull('deleted_at')->get();
        $categories = Category::all();

        // Pre-check which deliveries already have an item in inventory (by SKU)
        $existingSkus = [];
        $poIds = $deliveries->pluck('purchase_order_id')->filter()->unique()->values()->toArray();

        if (!empty($poIds)) {
            $products = DB::connection('procurement')
                ->table('purchase_order_items')
                ->join('supplier_products', 'purchase_order_items.supplier_product_id', '=', 'supplier_products.id')
                ->whereIn('purchase_order_items.purchase_order_id', $poIds)
                ->select('purchase_order_items.purchase_order_id', 'supplier_products.sku')
                ->get();

            $skus = $products->pluck('sku')->filter()->unique()->values()->toArray();
            $knownSkus = [];
            if (!empty($skus)) {
                $knownSkus = Item::whereIn('sku', $skus)->pluck('sku')->toArray();
            }

            $skusByPo = $products->groupBy('purchase_order_id')->map(fn ($items) => $items->pluck('sku')->filter()->unique()->values());
            foreach ($deliveries as $delivery) {
                $poSkus = $skusByPo->get($delivery->purchase_order_id, collect());
                $existingSkus[$delivery->shipment_number] = $poSkus->intersect($knownSkus)->isNotEmpty();
            }
        }

        return view('stock-receiving', [
            'deliveries' => $deliveries,
            'processedShipments' => $processedShipments,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'existingSkus' => $existingSkus,
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
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $delivery = Procurement::findOrFail($deliveryId);

        $result = $this->executeApproval($delivery, $validated);

        if ($result === true) {
            return back()->with('success', 'Delivery approved and stock updated.');
        }

        if ($result === 'Category is required for new items.') {
            return back()->withErrors(['category_id' => $result])->withInput();
        }

        return back()->withErrors(["del_action_{$delivery->id}" => $result]);
    }

    private function executeApproval(Procurement $delivery, array $validated): true|string
    {
        return DB::transaction(function () use ($delivery, $validated) {
            // Fetch procurement product data (sku, name, unit_price)
            $product = $delivery->getSupplierProduct();

            if (!$product) {
                return 'Could not fetch delivery from procurement.';
            }

            // Try to match existing item by SKU, or create new one
            $item = Item::where('sku', $product->sku)->first();

            if (!$item) {
                if (empty($validated['category_id'])) {
                    return 'Category is required for new items.';
                }

                $item = Item::create([
                    'sku' => $product->sku,
                    'name' => $product->item_name,
                    'category_id' => $validated['category_id'],
                    'unit_cost' => $product->unit_price,
                ]);
            }

            // Lock the stock level row FIRST — this is the serialization point.
            // Any concurrent request for the same item+warehouse will wait here.
            $stockLevel = StockLevel::where('item_id', $item->id)
                ->where('warehouse_id', $validated['warehouse_id'])
                ->lockForUpdate()
                ->first();

            $isNew = false;

            if (!$stockLevel) {
                $stockLevel = StockLevel::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'stock' => 0,
                    'reorder_threshold' => 10,
                ]);
                $isNew = true;
            }

            // NOW check if already processed — safe because we hold the exclusive lock.
            if (StockReceiving::where('shipment_number', $delivery->shipment_number)->where('item_id', $item->id)->exists()) {
                return 'This delivery has already been processed.';
            }

            if ($isNew) {
                $stockLevel->update(['stock' => $delivery->qty]);
            } else {
                $stockLevel->notification_source = 'receiving';
                $stockLevel->increment('stock', $delivery->qty);
            }

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
                'notes' => "From delivery - Shipment: {$delivery->shipment_number}",
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

        $delivery = Procurement::findOrFail($deliveryId);

        $result = DB::transaction(function () use ($delivery, $validated) {
            if (StockReceiving::where('shipment_number', $delivery->shipment_number)->exists()) {
                return 'This delivery has already been processed.';
            }

            StockReceiving::create([
                'shipment_number' => $delivery->shipment_number,
                'item_id' => null,
                'warehouse_id' => null,
                'quantity' => $delivery->qty,
                'status' => 'rejected',
                'processed_by' => Auth::id(),
                'remarks' => $validated['reject_reason'],
                'processed_at' => now(),
            ]);

            return true;
        });

        if ($result === true) {
            return back()->with('success', 'Delivery rejected.');
        }

        return back()->withErrors(["del_action_{$delivery->id}" => $result]);
    }
}
