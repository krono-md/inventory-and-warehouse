@extends('layouts.dashboard')

@section('title', 'Stock Receiving')

@section('content')
    <!-- Stat Cards Row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Pending POs</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $pendingCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Received Today</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $receivedTodayCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Discrepancies</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $discrepancyCount }}</p>
        </div>
    </div>

    <!-- Table Card -->
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;min-width:0;">
        <!-- Table -->
        <div class="responsive-table" style="min-width:0;">
            <table class="stock-table" style="width:100%;table-layout:auto;border-collapse:collapse;">
                <thead>
                    <tr style="background:#1b3a6b;">
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">PO REFERENCE</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ITEM NAME</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">EXPECTED QTY</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">RECEIVED QTY</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">DISCREPANCY</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">STATUS</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">DATE</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receivings as $receiving)
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $receiving->reference }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $receiving->item->name }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $receiving->expected_quantity }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $receiving->received_quantity ?? '—' }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $receiving->discrepancy ?? '—' }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ ucfirst($receiving->status) }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $receiving->created_at->format('M d, Y') }}</td>
                            <td style="text-align:center;padding:12px 8px;">
                                <button style="background:#1b6fc8;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;">Confirm Receipt</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:40px;color:#64748b;font-size:13px;">
                                <svg width="48" height="48" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" stroke-width="1.5" style="margin:0 auto 12px;display:block;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                No pending purchase orders from Procurement.<br>
                                <span style="font-size:12px;color:#94a3b8;">Incoming POs will appear here once Procurement sends them.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
