<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StockAdjustment::with(['item', 'warehouse']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($reason = $request->input('reason')) {
            $query->where('reason', $reason);
        }

        if ($warehouse = $request->input('warehouse')) {
            $query->where('warehouse_id', $warehouse);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($iq) use ($search) {
                    $iq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                       ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$search}%"]);
                });
            });
        }

        $adjustments = $query->orderByDesc('created_at')->paginate(10)->appends($request->query());

        $totalCount = StockAdjustment::count();
        $increaseCount = StockAdjustment::where('type', 'increase')->count();
        $pendingCount = StockAdjustment::where('status', 'pending')->count();

        $itemsByWarehouse = StockLevel::with('item')
            ->get()
            ->groupBy('warehouse_id')
            ->map(fn ($levels) => $levels->pluck('item')->unique('id')->values());

        return view('stock-adjustments', [
            'adjustments' => $adjustments,
            'warehouses' => Warehouse::all(),
            'items' => Item::all(),
            'itemsByWarehouse' => $itemsByWarehouse,
            'filters' => $request->only(['search', 'type', 'reason', 'warehouse', 'status']),
            'totalCount' => $totalCount,
            'increaseCount' => $increaseCount,
            'pendingCount' => $pendingCount,
            'activePage' => 'stock-adjustments',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'type' => 'required|in:increase,decrease',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|in:damage,expired,recount,theft,correction',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'pending';
        $adjustment = StockAdjustment::create($validated);

        if ($adjustment->quantity <= config('inventory.auto_approve_threshold', 5)) {
            $result = $this->executeApproval($adjustment);

            if ($result === true) {
                return back()->with('success', 'Adjustment auto-approved and stock updated.');
            }

            return back()->with('success', 'Adjustment submitted. Auto-approve skipped: ' . $result);
        }

        return back()->with('success', 'Adjustment request submitted for approval.');
    }

    public function approve(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return back()->with('error', 'This adjustment has already been processed.');
        }

        $result = $this->executeApproval($adjustment);

        if ($result === true) {
            return back()->with('success', 'Adjustment approved and stock updated.');
        }

        return back()->with('error', $result);
    }

    private function executeApproval(StockAdjustment $adjustment): true|string
    {
        return DB::transaction(function () use ($adjustment) {
            $adjustment = StockAdjustment::lockForUpdate()->find($adjustment->id);

            if ($adjustment->status !== 'pending') {
                return 'This adjustment has already been processed.';
            }

            $stockLevel = StockLevel::where('item_id', $adjustment->item_id)
                ->where('warehouse_id', $adjustment->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (!$stockLevel) {
                return 'No stock level record exists for this item and warehouse combination.';
            }

            if ($adjustment->type === 'decrease' && $stockLevel->quantity_on_hand < $adjustment->quantity) {
                return "Insufficient stock. Only {$stockLevel->quantity_on_hand} units available.";
            }

            $stockLevel->notification_source = 'inventory';

            if ($adjustment->type === 'increase') {
                $stockLevel->increment('quantity_on_hand', $adjustment->quantity);
            } else {
                $stockLevel->decrement('quantity_on_hand', $adjustment->quantity);
            }

            $adjustment->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            StockMovement::create([
                'type' => 'adjustment',
                'item_id' => $adjustment->item_id,
                'warehouse_id' => $adjustment->warehouse_id,
                'quantity' => $adjustment->type === 'decrease' ? -$adjustment->quantity : $adjustment->quantity,
                'reference' => 'ADJ-' . str_pad($adjustment->id, 6, '0', STR_PAD_LEFT),
                'notes' => "Adjustment #{$adjustment->id} approved: {$adjustment->type} ({$adjustment->reason})",
                'created_at' => now(),
            ]);

            return true;
        });
    }

    public function reject(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return back()->with('error', 'This adjustment has already been processed.');
        }

        $adjustment->update([
            'status' => 'rejected',
        ]);

        return back()->with('success', 'Adjustment rejected.');
    }
}
