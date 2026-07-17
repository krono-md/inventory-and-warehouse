@extends('layouts.dashboard')

@section('title', 'Item Catalog')

@push('styles')
    <style>
        .expand-row { display: none; }
        .expand-row.open { display: table-row; }
        .expand-toggle { cursor: pointer; transition: transform 0.2s ease; display: inline-block; }
        .expand-toggle.open { transform: rotate(90deg); }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        .status-In.Stock { background: #dcfce7; color: #166534; }
        .status-Low.Stock { background: #fef9c3; color: #854d0e; }
        .status-Out.of.Stock { background: #fee2e2; color: #991b1b; }

        /* Expandable sub-table animation */
        .expand-row {
            display: none;
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .expand-row.open {
            display: table-row;
            opacity: 1;
            transform: translateY(0);
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(34,197,94,0.15);color:#22c55e;border-radius:10px;font-weight:600;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Stat Cards Row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">In Stock</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $inStockCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Low Stock</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $lowStockCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Out of Stock</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $outOfStockCount }}</p>
        </div>
    </div>

<!-- Inventory Items Card -->
<div style="background:#ffffff;border-radius:20px;overflow:hidden;min-width:0;">
    <!-- Header: Title + Search + Filters -->
    <form method="GET" action="{{ route('item-catalog') }}" class="responsive-flex" style="padding:20px 24px 16px 24px;">
        <h2 style="font-size:18px;font-weight:700;color:#000;white-space:nowrap;margin-right:8px;">Inventory Items</h2>
        <!-- Search -->
        <div style="display:flex;align-items:center;background:#E2E8F0;border-radius:8px;padding:8px 14px;gap:8px;flex:1;min-width:150px;">
            <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Name, Category... " style="border:none;outline:none;background:transparent;font-size:13px;font-family:'Inter',sans-serif;color:#333;width:100%;">
        </div>
        <!-- Filter: Categories -->
        <select name="category" onchange="this.form.submit();" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ data_get($category, 'id') }}" {{ request('category') == data_get($category, 'id') ? 'selected' : '' }}>{{ data_get($category, 'name') }}</option>
            @endforeach
        </select>
        <!-- Filter: Warehouse -->
        <select name="warehouse" onchange="this.form.submit();" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
            <option value="">All Warehouse</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ data_get($warehouse, 'id') }}" {{ request('warehouse') == data_get($warehouse, 'id') ? 'selected' : '' }}>{{ data_get($warehouse, 'name') }}</option>
            @endforeach
        </select>
        <!-- Filter: Status -->
        <select name="status" onchange="this.form.submit();" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
            <option value="">All Status</option>
            <option value="In Stock" {{ request('status') === 'In Stock' ? 'selected' : '' }}>In Stock</option>
            <option value="Low Stock" {{ request('status') === 'Low Stock' ? 'selected' : '' }}>Low Stock</option>
            <option value="Out of Stock" {{ request('status') === 'Out of Stock' ? 'selected' : '' }}>Out of Stock</option>
        </select>
        <!-- Clear Filters -->
        @if(request()->anyFilled(['search', 'category', 'warehouse', 'status']))
            <a href="{{ route('item-catalog') }}" style="background:transparent;color:#64748b;border:1px solid #cbd5e1;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;flex-shrink:0;" title="Clear all filters">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </a>
        @endif
    </form>

    <!-- Table -->
    <div class="responsive-table" style="min-width:0;">
        <table class="stock-table" style="width:100%;table-layout:fixed;border-collapse:collapse;min-width:820px;">
            <thead>
                <tr style="background:#1b3a6b;">
                    <th style="width:30px;padding:12px 4px;"></th>
                    <th data-sort="name" style="text-align:center;padding:12px 4px;color:#fff;font-size:12px;font-weight:600;">ITEM NAME <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                    <th data-sort="category" style="text-align:center;padding:12px 4px;color:#fff;font-size:12px;font-weight:600;">CATEGORY <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                    <th data-sort="quantity" style="text-align:center;padding:12px 4px;color:#fff;font-size:12px;font-weight:600;">TOTAL STOCK <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                    <th data-sort="unit_cost" style="text-align:center;padding:12px 4px;color:#fff;font-size:12px;font-weight:600;">UNIT COST <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                    <th data-sort="status" style="text-align:center;padding:12px 4px;color:#fff;font-size:12px;font-weight:600;">STATUS <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                <tr style="background:{{ $loop->even ? '#F4F6FA' : '#ffffff' }}; border-top:1px solid #E2E8F0; cursor:pointer;" onclick="toggleExpand({{ $loop->index }})">
                    <td style="text-align:center;padding:12px 4px;">
                        <span class="expand-toggle" id="toggle-{{ $loop->index }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#64748b"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </td>
                    <td style="text-align:center;padding:12px 4px;font-size:13px;color:#132B52;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ data_get($item, 'name') }}</td>
                    <td style="text-align:center;padding:12px 4px;font-size:13px;color:#5B7A9D;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ data_get($item, 'category') }}</td>
                    <td style="text-align:center;padding:12px 4px;font-size:13px;color:#132B52;font-weight:600;">{{ data_get($item, 'total_stock') }}</td>
                    <td style="text-align:center;padding:12px 4px;font-size:13px;color:#132B52;">&#8369;{{ number_format(data_get($item, 'unit_cost'), 2) }}</td>
                    <td style="text-align:center;padding:12px 8px;">
                        @php
                            $badgeColors = [
                                'In Stock' => ['bg' => '#DCFCE7', 'text' => '#16A34A'],
                                'Low Stock' => ['bg' => '#FEF3C7', 'text' => '#D97706'],
                                'Out of Stock' => ['bg' => '#FEE2E2', 'text' => '#DC2626'],
                            ];
                            $colors = $badgeColors[data_get($item, 'status')];
                        @endphp
                        <span style="background:{{ $colors['bg'] }};color:{{ $colors['text'] }};font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;">{{ data_get($item, 'status') }}</span>
                    </td>
                </tr>
                <!-- Expandable per-warehouse breakdown -->
                <tr class="expand-row" id="expand-{{ $loop->index }}">
                    <td colspan="7" style="padding:0 16px 16px 40px;background:#f8fafc;">
                        <table style="width:100%;border-collapse:collapse;margin-top:4px;">
                            <thead>
                                <tr style="border-bottom:2px solid #e2e8f0;">
                                    <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Warehouse</th>
                                    <th style="text-align:center;padding:8px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">On Hand</th>
                                    <th style="text-align:center;padding:8px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Reserved</th>
                                    <th style="text-align:center;padding:8px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Available</th>
                                    <th style="text-align:center;padding:8px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Reorder Threshold</th>
                                    <th style="text-align:center;padding:8px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (data_get($item, 'stock_breakdown', []) as $row)
                                <tr style="border-bottom:1px solid #e2e8f0; background:#ffffff;">
                                    <td style="padding:8px 10px;font-size:12px;color:#0f172a;">{{ $row['warehouse'] }}</td>
                                    <td style="text-align:center;padding:8px 10px;font-size:12px;color:#0f172a;font-weight:600;">{{ $row['on_hand'] }}</td>
                                    <td style="text-align:center;padding:8px 10px;font-size:12px;color:#64748b;">{{ $row['reserved'] }}</td>
                                    <td style="text-align:center;padding:8px 10px;font-size:12px;color:#0f172a;font-weight:600;">{{ $row['available'] }}</td>
                                    <td style="text-align:center;padding:6px 10px;" onclick="event.stopPropagation()">
                                        <form method="POST" action="{{ route('stock-levels.update', $row['stock_level_id']) }}" style="display:inline-flex;align-items:center;gap:4px;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="number" name="reorder_threshold" value="{{ $row['reorder_threshold'] }}" min="0" class="threshold-input" style="min-width:48px;width:calc({{ max(1, strlen((string) $row['reorder_threshold'])) }}ch + 22px);padding:5px 8px;background:#ffffff;color:#0f172a;border:1px solid #94a3b8;border-radius:6px;font-size:12px;text-align:center;outline:none;box-shadow:inset 0 1px 2px rgba(0,0,0,0.06);" onfocus="this.style.borderColor='#1b6fc8';this.style.boxShadow='0 0 0 3px rgba(27,111,200,0.15)'" onblur="this.style.borderColor='#94a3b8';this.style.boxShadow='inset 0 1px 2px rgba(0,0,0,0.06)'" oninput="this.style.width = 'calc(' + Math.max(1, this.value.length) + 'ch + 22px)'">
                                            <button type="submit" style="background:#1b6fc8;color:#fff;border:none;border-radius:4px;padding:3px 6px;font-size:10px;cursor:pointer;font-weight:600;">Save</button>
                                        </form>
                                    </td>
                                    <td style="text-align:center;padding:8px 10px;">
                                        @php
                                            $slColors = [
                                                'In Stock' => ['bg' => '#dcfce7', 'text' => '#166534'],
                                                'Low Stock' => ['bg' => '#fef9c3', 'text' => '#854d0e'],
                                                'Out of Stock' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
                                            ];
                                            $slc = $slColors[$row['status']] ?? ['bg' => '#e2e8f0', 'text' => '#64748b'];
                                        @endphp
                                        <span style="background:{{ $slc['bg'] }};color:{{ $slc['text'] }};font-size:10px;font-weight:600;padding:3px 8px;border-radius:20px;">{{ $row['status'] }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:12px;color:#94a3b8;font-size:12px;">No stock records for this item.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:32px;color:#94a3b8;font-size:13px;">
                        No items found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $items->links() }}
</div>
@endsection

@push('scripts')
<script>
    function toggleExpand(index) {
        const row = document.getElementById('expand-' + index);
        const toggle = document.getElementById('toggle-' + index);
        row.classList.toggle('open');
        toggle.classList.toggle('open');
    }
</script>
@endpush
