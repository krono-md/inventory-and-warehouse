@extends('layouts.dashboard')

@section('title', 'Stock Levels')

@push('styles')
<style>
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .status-in_stock { background: #dcfce7; color: #166534; }
    .status-low_stock { background: #fef9c3; color: #854d0e; }
    .status-out_of_stock { background: #fee2e2; color: #991b1b; }
    .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 16px; }
    .pagination a, .pagination span { padding: 6px 12px; border-radius: 8px; font-size: 12px; background: #e2e8f0; color: #0f172a; text-decoration: none; }
    .pagination .active { background: #1b3a6b; color: #fff; }
    .pagination .disabled { opacity: 0.5; }
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
            <p style="font-size:15px;color:#94a3b8;">In Stock Items</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $inStockCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Low Stock Items</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $lowStockCount }}</p>
        </div>
        <div style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:15px;color:#94a3b8;">Out of Stock Items</p>
            <p style="font-size:40px;font-weight:bold;color:#fff;">{{ $outOfStockCount }}</p>
        </div>
    </div>

    <!-- Table Card -->
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;min-width:0;">
        <!-- Filters Row -->
        <form method="GET" action="{{ route('stock-levels') }}" style="padding:16px 20px;display:flex;align-items:center;gap:12px;flex-wrap:nowrap;min-width:0;">
            <!-- Search -->
            <div style="display:flex;align-items:center;background:#E2E8F0;border-radius:8px;padding:8px 14px;gap:8px;flex:1;min-width:150px;">
                <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by Name, Sku, Category..." style="border:none;outline:none;background:transparent;font-size:12px;font-family:'Inter',sans-serif;color:#333;width:100%;">
            </div>
            <!-- Filter: Categories -->
            <select name="category" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ data_get($category, 'id') }}" {{ ($filters['category'] ?? '') == data_get($category, 'id') ? 'selected' : '' }}>{{ data_get($category, 'name') }}</option>
                @endforeach
            </select>
            <!-- Filter: Warehouses -->
            <select name="warehouse" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">Warehouses</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ data_get($warehouse, 'id') }}" {{ ($filters['warehouse'] ?? '') == data_get($warehouse, 'id') ? 'selected' : '' }}>{{ data_get($warehouse, 'name') }}</option>
                @endforeach
            </select>
            <!-- Filter: Status -->
            <select name="status" onchange="this.form.submit()" style="background:#E2E8F0;color:#000;border:none;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                <option value="">Status</option>
                <option value="in_stock" {{ ($filters['status'] ?? '') === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                <option value="low_stock" {{ ($filters['status'] ?? '') === 'low_stock' ? 'selected' : '' }}>Low Stock</option>
                <option value="out_of_stock" {{ ($filters['status'] ?? '') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
            </select>
            <!-- Clear Filters -->
            @if(array_filter($filters ?? []))
                <a href="{{ route('stock-levels') }}" style="background:transparent;color:#64748b;border:1px solid #cbd5e1;border-radius:20px;padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;flex-shrink:0;" title="Clear all filters">
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
                        <th data-sort="sku" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">SKU <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="item_name" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ITEM NAME <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="category" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">CATEGORY <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="warehouse" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">WAREHOUSE <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="on_hand" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ON HAND <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="reserved" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">RESERVED <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="available" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">AVAILABLE <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="reorder_threshold" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">MIN DAYS <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="days_remaining" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">DAYS LEFT <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                        <th data-sort="status" style="text-align:center;padding:10px 6px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">STATUS <span class="sort-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5l-6.5 7h13L12 3.5z"/><path d="M12 20.5l6.5-7h-13l6.5 7z"/></svg></span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockLevels as $level)
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->item->sku }}</td>
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->item->name }}</td>
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->item->category->name ?? '-' }}</td>
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->warehouse?->name ?? 'Deleted' }}</td>
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->quantity_on_hand }}</td>
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->quantity_reserved }}</td>
                            <td style="text-align:center;padding:12px 6px;color:#0f172a;font-size:12px;">{{ $level->available }}</td>
                            <td style="text-align:center;padding:8px 6px;color:#0f172a;font-size:12px;">
                                <form method="POST" action="{{ route('stock-levels.update', $level) }}" style="display:inline-flex;align-items:center;gap:4px;">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="reorder_threshold" value="{{ $level->reorder_threshold }}" min="0" style="width:60px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;text-align:center;outline:none;" onfocus="this.style.borderColor='#1b6fc8'" onblur="this.style.borderColor='#e2e8f0'">
                                    <button type="submit" style="background:#1b6fc8;color:#fff;border:none;border-radius:4px;padding:4px 8px;font-size:10px;cursor:pointer;font-weight:600;">Save</button>
                                </form>
                            </td>
                            <td style="text-align:center;padding:12px 6px;font-size:12px;font-weight:600;{{ $level->days_remaining !== null && $level->reorder_threshold > 0 && $level->days_remaining <= $level->reorder_threshold ? 'color:#dc2626;' : 'color:#0f172a;' }}">
                                {{ $level->days_remaining !== null ? $level->days_remaining . 'd' : '—' }}
                            </td>
                            <td style="text-align:center;padding:12px 6px;">
                                <span class="status-badge status-{{ $level->status }}">{{ $level->status_label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align:center;padding:24px;color:#64748b;font-size:13px;">No stock levels found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $stockLevels->links() }}
    </div>
@endsection
