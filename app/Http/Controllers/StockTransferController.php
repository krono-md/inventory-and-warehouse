<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $query = StockTransfer::with(['item', 'fromWarehouse', 'toWarehouse']);

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
                $iq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                   ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$search}%"]);
            });
        }

        $transfers = $query->orderByDesc('created_at')->paginate(10)->appends($request->query());

        $totalCount = StockTransfer::count();
        $pendingCount = StockTransfer::where('status', 'pending')->count();
        $approvedCount = StockTransfer::where('status', 'approved')->count();

        return view('stock-transfers', [
            'transfers' => $transfers,
            'warehouses' => Warehouse::all(),
            'items' => Item::all(),
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
            'from_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'to_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at'), 'different:from_warehouse_id'],
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'pending';
        $validated['requested_by'] = 'System';

        StockTransfer::create($validated);

        return back()->with('success', 'Transfer request submitted for approval.');
    }

    public function approve(StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return back()->with('error', 'This transfer has already been processed.');
        }

        $result = $this->executeApproval($transfer);

        if ($result === true) {
            return back()->with('success', 'Transfer approved and stock moved.');
        }

        return back()->with('error', $result);
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

            if ($source->quantity_on_hand < $transfer->quantity) {
                return "Insufficient stock in source warehouse. Only {$source->quantity_on_hand} units available.";
            }

            if (!$destination) {
                $destination = StockLevel::create([
                    'item_id' => $transfer->item_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'reorder_threshold' => $source->reorder_threshold,
                ]);
            }

            $reference = $transfer->reference;
            $now = now();

            $source->notification_source = 'transfer';
            $source->decrement('quantity_on_hand', $transfer->quantity);

            $destination->notification_source = 'transfer';
            $destination->increment('quantity_on_hand', $transfer->quantity);

            StockMovement::create([
                'type' => 'transfer',
                'item_id' => $transfer->item_id,
                'warehouse_id' => $transfer->from_warehouse_id,
                'quantity' => $transfer->quantity,
                'reference' => $reference,
                'notes' => "Transfer #{$transfer->id} from {$transfer->fromWarehouse->name} to {$transfer->toWarehouse->name}",
                'created_at' => $now,
            ]);

            $transfer->update([
                'status' => 'approved',
                'approved_by' => 'System',
                'approved_at' => $now,
            ]);

            return true;
        });
    }

    public function reject(StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return back()->with('error', 'This transfer has already been processed.');
        }

        $transfer->update(['status' => 'rejected']);

        return back()->with('success', 'Transfer rejected.');
    }
}
