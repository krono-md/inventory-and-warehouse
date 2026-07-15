<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::with(['item', 'warehouse', 'resolver']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($warehouse = $request->input('warehouse')) {
            $query->where('warehouse_id', $warehouse);
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

        $notifications = $query->orderByDesc('created_at')->paginate(10)->appends($request->query());

        $allNotifications = Notification::all();

        return view('notifications', [
            'notifications' => $notifications,
            'openCount' => $allNotifications->where('status', 'open')->count(),
            'resolvedTodayCount' => $allNotifications->where('status', 'resolved')->where('resolved_at', '>=', now()->startOfDay())->count(),
            'repeatCount' => DB::table('notifications')
                ->select('item_id')
                ->groupBy('item_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()->count(),
            'filters' => $request->only(['search', 'status', 'type', 'warehouse']),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'activePage' => 'notifications',
        ]);
    }

    public function acknowledge(Notification $notification)
    {
        if ($notification->status !== 'open') {
            return back()->with('error', 'This notification has already been processed.');
        }

        $notification->update(['status' => 'acknowledged']);

        return back()->with('success', 'Notification acknowledged.');
    }

    public function resolve(Notification $notification)
    {
        if ($notification->status === 'resolved') {
            return back()->with('error', 'This notification is already resolved.');
        }

        $notification->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Notification resolved.');
    }
}
