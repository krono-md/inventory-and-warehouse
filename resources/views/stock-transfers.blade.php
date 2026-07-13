@extends('layouts.dashboard')

@section('title', 'Stock Transfers')

@push('styles')
<style>
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    #transferModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #transferModal.open { opacity: 1; pointer-events: auto; }

    .form-input { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #0f172a; font-family: 'Inter', sans-serif; outline: none; }
    .form-input:focus { border-color: #1b6fc8; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
    .form-error { color: #ef4444; font-size: 11px; margin-top: 4px; }
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
            <p style="font-size:15px;color:#94a3b8;">Total Transfers</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $totalCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Pending Approval</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $pendingCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Approved</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $approvedCount }}</p>
        </div>
    </div>

    <!-- Table Card -->
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;min-width:0;">
        <!-- Filters Row -->
        <form method="GET" action="{{ route('stock-transfers') }}" style="padding:16px 20px;display:flex;align-items:center;gap:12px;flex-wrap:nowrap;min-width:0;">
            <!-- Search -->
            <div style="display:flex;align-items:center;background:#E2E8F0;border-radius:8px;padding:8px 14px;gap:8px;flex:1;min-width:150px;">
                <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by Name, SKU..." style="border:none;outline:none;background:transparent;font-size:12px;font-family:'Inter',sans-serif;color:#333;width:100%;">
            </div>
            <!-- Filter: Status -->
            <select name="status" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">Status</option>
                <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            <!-- Filter: From Warehouse -->
            <select name="from_warehouse" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">From Warehouse</option>
                @foreach ($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['from_warehouse'] ?? '') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
            <!-- Filter: To Warehouse -->
            <select name="to_warehouse" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">To Warehouse</option>
                @foreach ($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['to_warehouse'] ?? '') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
            <!-- + New Transfer Button -->
            <button type="button" onclick="openTransferModal()" style="background:#1b6fc8;color:#fff;border:none;border-radius:20px;padding:8px 20px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Transfer
            </button>
        </form>

        <!-- Table -->
        <div class="responsive-table" style="min-width:0;">
            <table class="stock-table" style="width:100%;table-layout:auto;border-collapse:collapse;">
                <thead>
                    <tr style="background:#1b3a6b;">
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">TRF.ID</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ITEM NAME</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">SKU</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">FROM</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">TO</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">QUANTITY</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">STATUS</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">APPROVED BY</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">DATE</th>
                        <th style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $transfer->reference }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $transfer->item->name }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;">{{ $transfer->item->sku }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $transfer->fromWarehouse?->name ?? 'Deleted' }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $transfer->toWarehouse?->name ?? 'Deleted' }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#132B52;font-weight:600;">{{ $transfer->quantity }}</td>
                            <td style="text-align:center;padding:12px 8px;">
                                <span class="status-badge status-{{ $transfer->status }}">{{ ucfirst($transfer->status) }}</span>
                            </td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $transfer->approved_by ?? '—' }}</td>
                            <td style="text-align:center;padding:12px 8px;font-size:13px;color:#5B7A9D;">{{ $transfer->created_at->format('M d, Y') }}</td>
                            <td style="text-align:center;padding:12px 8px;">
                                @if($transfer->status === 'pending')
                                    <form method="POST" action="{{ route('stock-transfers.approve', $transfer) }}" style="display:inline;" onsubmit="return confirm('Approve this transfer?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" style="background:#166534;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('stock-transfers.reject', $transfer) }}" style="display:inline;" onsubmit="return confirm('Reject this transfer?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" style="background:#991b1b;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;">Reject</button>
                                    </form>
                                @else
                                    <span style="color:#94a3b8;font-size:12px;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align:center;padding:20px;color:#64748b;font-size:13px;">No stock transfers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $transfers->links() }}
    </div>

    <div id="transferModal" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:20;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:18px;padding:28px;width:100%;max-width:560px;margin:16px;box-shadow:0 10px 30px rgba(0,0,0,0.4);color:#0f172a;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
                <h2 style="font-size:20px;font-weight:700;">New Stock Transfer</h2>
                <button onclick="closeTransferModal()" style="background:transparent;border:none;color:#64748b;cursor:pointer;font-size:24px;line-height:1;">&times;</button>
            </div>

            <form method="POST" action="{{ route('stock-transfers.store') }}" novalidate>
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column: span 2;">
                        <label class="form-label">Item</label>
                        <select name="item_id" class="form-input" required>
                            <option value="">Select Item</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="form-label">From Warehouse</label>
                        <select name="from_warehouse_id" class="form-input" required>
                            <option value="">Select Source</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('from_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('from_warehouse_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="form-label">To Warehouse</label>
                        <select name="to_warehouse_id" class="form-input" required>
                            <option value="">Select Destination</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('to_warehouse_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" value="{{ old('quantity') }}" min="1" class="form-input" placeholder="e.g. 50" required>
                        @error('quantity')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-input" placeholder="Additional details...">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:24px;">
                    <button type="button" onclick="closeTransferModal()" style="background:transparent;color:#64748b;border:1px solid #e2e8f0;border-radius:8px;padding:10px 18px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#1b6fc8;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-weight:600;cursor:pointer;">Submit Transfer</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const transferModal = document.getElementById('transferModal');
    function openTransferModal() { transferModal.classList.add('open'); }
    function closeTransferModal() { transferModal.classList.remove('open'); }
    transferModal.addEventListener('click', function(e) { if (e.target === this) closeTransferModal(); });

    @if($errors->any())
        openTransferModal();
    @endif
</script>
@endpush
