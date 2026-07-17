@extends('layouts.dashboard')

@section('title', 'Stock Receiving')

@push('styles')
<style>
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-in-transit { background: #dbeafe; color: #1e40af; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    #approveModal, #rejectModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #approveModal.open, #rejectModal.open { opacity: 1; pointer-events: auto; }
</style>
@endpush

@section('content')
    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(34,197,94,0.15);color:#22c55e;border-radius:10px;font-weight:600;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(239,68,68,0.15);color:#ef4444;border-radius:10px;font-weight:600;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Stat Cards Row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Pending Deliveries</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $pendingCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Received Today</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $receivedTodayCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Rejected</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $rejectedCount }}</p>
        </div>
    </div>

    <!-- Table Card -->
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;min-width:0;">
        <!-- Filters Row -->
        <form method="GET" action="{{ route('stock-receiving') }}" style="padding:16px 20px;display:flex;align-items:center;gap:12px;flex-wrap:nowrap;min-width:0;">
            <!-- Search -->
            <div style="display:flex;align-items:center;background:#E2E8F0;border-radius:8px;padding:8px 14px;gap:8px;flex:1;min-width:150px;">
                <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by Shipment Number, Item..." style="border:none;outline:none;background:transparent;font-size:12px;font-family:'Inter',sans-serif;color:#333;width:100%;">
            </div>
            <!-- Filter: Status -->
            <select name="status" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">All Status</option>
                <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="in transit" {{ ($filters['status'] ?? '') === 'in transit' ? 'selected' : '' }}>In Transit</option>
            </select>
            <!-- Clear Filters -->
            @if(array_filter($filters ?? []))
                <a href="{{ route('stock-receiving') }}" style="background:transparent;color:#64748b;border:1px solid #cbd5e1;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;flex-shrink:0;" title="Clear all filters">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </a>
            @endif
        </form>

        <!-- Table -->
        <div class="responsive-table" style="min-width:0;">
            <table class="stock-table" style="width:100%;table-layout:auto;border-collapse:collapse;">
                <thead>
                    <tr style="background:#1b3a6b;">
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">SHIPMENT #</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ITEM NAME</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">QUANTITY</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">STATUS</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">DELIVERY DATE</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">REMARKS</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        @php
                            $isProcessed = in_array($delivery->shipment_number, $processedShipments);
                        @endphp
                        <tr style="border-bottom:1px solid #e2e8f0;{{ $isProcessed ? 'opacity:0.5;' : '' }}">
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $delivery->shipment_number }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $delivery->items }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;font-weight:600;">{{ $delivery->qty }}</td>
                            <td style="text-align:center;padding:12px 8px;">
                                <span class="status-badge status-{{ str_replace(' ', '-', strtolower($delivery->status)) }}">{{ ucfirst($delivery->status) }}</span>
                            </td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $delivery->delivery_date?->format('M d, Y') ?? '—' }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $delivery->remarks ?? '—' }}</td>
                            <td style="text-align:center;padding:12px 8px;">
                                @if(!$isProcessed)
                                    <button onclick="openApproveModal({{ $delivery->id }}, '{{ $delivery->items }}', {{ $delivery->qty }})" style="background:#166534;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;">Approve</button>
                                    <button onclick="openRejectModal({{ $delivery->id }})" style="background:#991b1b;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;">Reject</button>
                                @else
                                    <span style="color:#94a3b8;font-size:12px;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;color:#64748b;font-size:13px;">
                                <svg width="48" height="48" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" stroke-width="1.5" style="margin:0 auto 12px;display:block;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                No pending deliveries from Procurement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($deliveries->hasPages())
            <div style="padding:16px;border-top:1px solid #e2e8f0;">
                {{ $deliveries->links() }}
            </div>
        @endif
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:20px;padding:24px;width:90%;max-width:500px;">
            <h3 style="margin:0 0 16px 0;font-size:18px;color:#0b1e3d;">Approve Delivery</h3>
            <form id="approveForm" method="POST" action="">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Item Name</label>
                    <input type="text" id="approveItemName" disabled style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Quantity</label>
                    <input type="number" id="approveQty" disabled style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Existing Item? <span style="color:#94a3b8;font-size:11px;">(Leave empty if new item)</span></label>
                    <select name="item_id" id="existingItemSelect" onchange="toggleNewItemFields()" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                        <option value="">-- Create New Item --</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="newItemFields">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">New Item Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="item_name" id="newItemName" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Category <span style="color:#dc2626;">*</span></label>
                        <select name="category_id" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Unit Cost</label>
                        <input type="number" name="unit_cost" step="0.01" min="0" placeholder="0.00" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Warehouse <span style="color:#dc2626;">*</span></label>
                    <select name="warehouse_id" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                        <option value="">-- Select Warehouse --</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" onclick="closeApproveModal()" style="background:#e2e8f0;color:#475569;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#166534;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;">Approve & Receive</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:20px;padding:24px;width:90%;max-width:400px;">
            <h3 style="margin:0 0 16px 0;font-size:18px;color:#0b1e3d;">Reject Delivery</h3>
            <form id="rejectForm" method="POST" action="">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:6px;">Reason for Rejection <span style="color:#dc2626;">*</span></label>
                    <textarea name="reject_reason" required rows="4" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" onclick="closeRejectModal()" style="background:#e2e8f0;color:#475569;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#991b1b;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApproveModal(deliveryId, itemName, qty) {
            document.getElementById('approveModal').style.display = 'flex';
            document.getElementById('approveModal').classList.add('open');
            document.getElementById('approveForm').action = '/stock-receiving/' + deliveryId + '/approve';
            document.getElementById('approveItemName').value = itemName;
            document.getElementById('approveQty').value = qty;
            document.getElementById('newItemName').value = itemName;
        }

        function closeApproveModal() {
            document.getElementById('approveModal').style.display = 'none';
            document.getElementById('approveModal').classList.remove('open');
            document.getElementById('approveForm').reset();
        }

        function openRejectModal(deliveryId) {
            document.getElementById('rejectModal').style.display = 'flex';
            document.getElementById('rejectModal').classList.add('open');
            document.getElementById('rejectForm').action = '/stock-receiving/' + deliveryId + '/reject';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('rejectModal').classList.remove('open');
            document.getElementById('rejectForm').reset();
        }

        function toggleNewItemFields() {
            const existingItemSelect = document.getElementById('existingItemSelect');
            const newItemFields = document.getElementById('newItemFields');
            
            if (existingItemSelect.value) {
                newItemFields.style.display = 'none';
            } else {
                newItemFields.style.display = 'block';
            }
        }

        // Close modal when clicking outside
        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) closeApproveModal();
        });
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
@endsection
