<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $query = StockTransfer::with(['item', 'fromWarehouse', 'toWarehouse', 'approver', 'requester']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($fromWarehouse = $request->input('from_warehouse')) {
            $query->where('from_warehouse_id', $fromWarehouse);
        }

        if ($toWarehouse = $request->input('to_warehouse')) {
            $query->where('to_warehouse_id', $toWarehouse);
        }

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->whereHas('item', function ($iq) use ($search) {
                $iq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
            });
        }

        $transfers = $query->orderByDesc('created_at')->paginate(10)->appends($request->query());

        $totalCount = StockTransfer::count();
        $pendingCount = StockTransfer::where('status', 'pending')->count();
        $approvedCount = StockTransfer::where('status', 'approved')->count();

        $stockLevels = StockLevel::with('item')->get();

        $itemsByWarehouse = $stockLevels
            ->where('stock', '>', 0)
            ->groupBy('warehouse_id')
            ->map(fn ($levels) => $levels->pluck('item')->unique('id')->values());

        $stockMap = $stockLevels->mapWithKeys(
            fn ($sl) => [$sl->warehouse_id . '-' . $sl->item_id => $sl->stock]
        );

        return view('stock-transfers', [
            'transfers' => $transfers,
            'warehouses' => Warehouse::all(),
            'items' => Item::all(),
            'itemsByWarehouse' => $itemsByWarehouse,
            'stockMap' => $stockMap,
            'filters' => $request->only(['search', 'status', 'from_warehouse', 'to_warehouse']),
            'totalCount' => $totalCount,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'activePage' => 'stock-transfers',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'from_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'to_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')->where('status', 'active'), 'different:from_warehouse_id'],
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        // Enforce quantity <= available stock in the selected source warehouse.
        $stockLevel = StockLevel::where('item_id', $validated['item_id'])
            ->where('warehouse_id', $validated['from_warehouse_id'])
            ->first();

        if (!$stockLevel) {
            return back()
                ->withErrors(['quantity' => 'No stock level record exists for the selected source warehouse.'])
                ->withInput();
        }

        if ($stockLevel->stock < $validated['quantity']) {
            return back()
                ->withErrors(['quantity' => "Insufficient stock in source warehouse. Only {$stockLevel->stock} units available."])
                ->withInput();
        }

        $validated['status'] = 'pending';
        $validated['requested_by'] = Auth::id();
        $validated['requested_by_user_id'] = Auth::id();

        StockTransfer::create($validated);

        return back()->with('success', 'Transfer request submitted for approval.');
    }

    public function approve(StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return back()->withErrors(["trf_action_{$transfer->id}" => 'This transfer has already been processed.']);
        }

        if ($transfer->requested_by_user_id === Auth::id()) {
            return back()->withErrors(["trf_action_{$transfer->id}" => 'You cannot approve your own transfer request.']);
        }

        $result = $this->executeApproval($transfer);

        if ($result === true) {
            return back()->with('success', 'Transfer approved and stock moved.');
        }

        return back()->withErrors(["trf_action_{$transfer->id}" => $result]);
    }

    private function executeApproval(StockTransfer $transfer): true|string
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::lockForUpdate()->find($transfer->id);

            if ($transfer->status !== 'pending') {
                return 'This transfer has already been processed.';
            }

            $source = StockLevel::where('item_id', $transfer->item_id)
                ->where('warehouse_id', $transfer->from_warehouse_id)
                ->lockForUpdate()
                ->first();

            $destination = StockLevel::where('item_id', $transfer->item_id)
                ->where('warehouse_id', $transfer->to_warehouse_id)
                ->lockForUpdate()
                ->first();

            if (!$source) {
                return 'No stock level record exists for the source warehouse.';
            }

            if ($source->stock < $transfer->quantity) {
                return "Insufficient stock in source warehouse. Only {$source->stock} units available.";
            }

            if (!$destination) {
                $destination = StockLevel::create([
                    'item_id' => $transfer->item_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'stock' => 0,
                    'reorder_threshold' => $source->reorder_threshold,
                ]);
            }

            $reference = $transfer->reference;
            $now = now();

            $source->notification_source = 'transfer';
            $source->decrement('stock', $transfer->quantity);

            $destination->notification_source = 'transfer';
            $destination->increment('stock', $transfer->quantity);

            Warehouse::whereIn('id', [$transfer->from_warehouse_id, $transfer->to_warehouse_id])
                ->update(['last_activity_at' => $now]);

            StockMovement::create([
                'type' => 'transfer',
                'item_id' => $transfer->item_id,
                'warehouse_id' => $transfer->from_warehouse_id,
                'quantity' => $transfer->quantity,
                'reference' => $reference,
                'notes' => "Transfer #{$transfer->id} from {$transfer->fromWarehouse->name} to {$transfer->toWarehouse->name}",
                'performed_by' => Auth::id(),
                'created_at' => $now,
            ]);

            StockMovement::create([
                'type' => 'transfer',
                'item_id' => $transfer->item_id,
                'warehouse_id' => $transfer->to_warehouse_id,
                'quantity' => $transfer->quantity,
                'reference' => $reference,
                'notes' => "Transfer #{$transfer->id} from {$transfer->fromWarehouse->name} to {$transfer->toWarehouse->name}",
                'performed_by' => Auth::id(),
                'created_at' => $now,
            ]);

            $transfer->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => $now,
            ]);

            return true;
        });
    }

    public function reject(StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return back()->withErrors(["trf_action_{$transfer->id}" => 'This transfer has already been processed.']);
        }

        if ($transfer->requested_by_user_id === Auth::id()) {
            return back()->withErrors(["trf_action_{$transfer->id}" => 'You cannot reject your own transfer request.']);
        }

        $transfer->update(['status' => 'rejected']);

        return back()->with('success', 'Transfer rejected.');
    }

    public function cancel(StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return back()->withErrors(["trf_action_{$transfer->id}" => 'Only pending transfers can be cancelled.']);
        }

        if ($transfer->requested_by_user_id !== Auth::id()) {
            return back()->withErrors(["trf_action_{$transfer->id}" => 'You can only cancel your own transfer requests.']);
        }

        $transfer->update(['status' => 'cancelled']);

        return back()->with('success', 'Transfer request cancelled.');
    }
}
