<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StockAdjustment::with(['item', 'warehouse', 'requester', 'approver']);

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
        $netAdjustedUnits = StockAdjustment::where('status', 'approved')
            ->selectRaw("SUM(CASE WHEN type = 'increase' THEN quantity ELSE -quantity END) as net")
            ->value('net') ?? 0;
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
            'netAdjustedUnits' => $netAdjustedUnits,
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
        $validated['requested_by'] = Auth::id();
        $adjustment = StockAdjustment::create($validated);

        return back()->with('success', 'Adjustment request submitted for approval.');
    }

    public function approve(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return back()->with('error', 'This adjustment has already been processed.');
        }

        if ($adjustment->requested_by === Auth::id()) {
            return back()->with('error', 'You cannot approve your own adjustment request.');
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
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            StockMovement::create([
                'type' => 'adjustment',
                'item_id' => $adjustment->item_id,
                'warehouse_id' => $adjustment->warehouse_id,
                'quantity' => $adjustment->type === 'decrease' ? -$adjustment->quantity : $adjustment->quantity,
                'reference' => 'ADJ-' . str_pad($adjustment->id, 6, '0', STR_PAD_LEFT),
                'notes' => "Adjustment #{$adjustment->id} approved: {$adjustment->type} ({$adjustment->reason})",
                'performed_by' => Auth::id(),
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

        if ($adjustment->requested_by === Auth::id()) {
            return back()->with('error', 'You cannot reject your own adjustment request.');
        }

        $adjustment->update([
            'status' => 'rejected',
        ]);

        return back()->with('success', 'Adjustment rejected.');
    }

    public function cancel(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return back()->with('error', 'Only pending adjustments can be cancelled.');
        }

        if ($adjustment->requested_by !== Auth::id()) {
            return back()->with('error', 'You can only cancel your own adjustment requests.');
        }

        $adjustment->update(['status' => 'cancelled']);

        return back()->with('success', 'Adjustment request cancelled.');
    }
}
