<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::withCount(['stockLevels as item_types_count' => function ($query) {
            $query->where('stock', '>', 0)->select(\Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT item_id)'));
        }])->get();

        return view('warehouse', [
            'warehouses' => $warehouses,
            'activePage' => 'warehouse',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'barangay' => 'required|string|max:100',
            'address_description' => 'required|string',
            'capacity_units' => 'required|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['country'] = 'Philippines';

        Warehouse::create($validated);

        return redirect()->route('warehouse')->with('success', 'Warehouse created successfully.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'barangay' => 'nullable|string|max:100',
            'address_description' => 'nullable|string',
            'capacity_units' => 'required|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $warehouse->update($validated);

        return redirect()->route('warehouse')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->used_units > 0) {
            return back()->with('error', 'Cannot deactivate warehouse with stock. Relocate items first.');
        }

        $warehouse->update(['status' => 'inactive']);
        $warehouse->delete();

        return redirect()->route('warehouse')->with('success', 'Warehouse deactivated.');
    }
}
