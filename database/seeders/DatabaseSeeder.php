<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedWarehouses();
        $this->seedItems();
        $this->seedStockData();
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Raw Material'],
            ['name' => 'Component'],
            ['name' => 'Finished Product'],
            ['name' => 'Consumable'],
            ['name' => 'Spare Part'],
            ['name' => 'Packaging'],
            ['name' => 'Tool / Equipment'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }
    }

    private function seedWarehouses(): void
    {
        $warehouses = [
            ['name' => 'Dasma Main Warehouse',    'province' => 'Cavite',       'city' => 'Dasmariñas', 'barangay' => 'Salawag',    'address_description' => 'Blk 5 Lot 12, Salawag',    'capacity_units' => 5000, 'status' => 'active'],
            ['name' => 'Manila Distribution Hub',  'province' => 'Metro Manila', 'city' => 'Manila',     'barangay' => 'Port Area',  'address_description' => 'Port Area, near Del Pan',   'capacity_units' => 3500, 'status' => 'active'],
            ['name' => 'Laguna South Storage',     'province' => 'Laguna',       'city' => 'Calamba',    'barangay' => 'Real',       'address_description' => 'Barangay Real, near SLEX',  'capacity_units' => 4200, 'status' => 'active'],
            ['name' => 'Clark North Depot',        'province' => 'Pampanga',     'city' => 'Angeles',    'barangay' => null,         'address_description' => 'Clark Freeport Zone',       'capacity_units' => 6000, 'status' => 'inactive'],
        ];

        foreach ($warehouses as $w) {
            Warehouse::create($w);
        }
    }

    private function seedItems(): void
    {
        $items = [
            ['sku' => 'CPU-INT-I5-001',    'name' => 'Intel Core i5-12400',             'category' => 'Component', 'unit_cost' => 9490.00],
            ['sku' => 'CPU-INT-I7-002',    'name' => 'Intel Core i7-13700K',            'category' => 'Component', 'unit_cost' => 18990.00],
            ['sku' => 'CPU-AMD-R5-003',    'name' => 'AMD Ryzen 5 7600X',               'category' => 'Component', 'unit_cost' => 11290.00],
            ['sku' => 'RAM-DDR4-16G-004',  'name' => 'Kingston Fury 16GB DDR4 3200MHz', 'category' => 'Component', 'unit_cost' => 2490.00],
            ['sku' => 'RAM-DDR5-32G-005',  'name' => 'Corsair Vengeance 32GB DDR5',     'category' => 'Component', 'unit_cost' => 5890.00],
            ['sku' => 'SSD-NVME-1TB-006',  'name' => 'Samsung 980 NVMe 1TB',            'category' => 'Component', 'unit_cost' => 4790.00],
            ['sku' => 'SSD-SATA-512G-007', 'name' => 'Crucial MX500 500GB SATA SSD',    'category' => 'Component', 'unit_cost' => 2490.00],
            ['sku' => 'HDD-4TB-008',       'name' => 'Seagate Barracuda 4TB HDD',       'category' => 'Component', 'unit_cost' => 3990.00],
            ['sku' => 'CPU-AMD-R7-009',    'name' => 'AMD Ryzen 7 7800X3D',             'category' => 'Component', 'unit_cost' => 21990.00],
            ['sku' => 'RAM-DDR4-8G-010',   'name' => 'Crucial Basic 8GB DDR4',          'category' => 'Component', 'unit_cost' => 1290.00],
        ];

        foreach ($items as $item) {
            $categoryId = Category::where('name', $item['category'])->value('id');
            Item::create([
                'sku' => $item['sku'],
                'name' => $item['name'],
                'category_id' => $categoryId,
                'unit_cost' => $item['unit_cost'],
            ]);
        }
    }

    private function seedStockData(): void
    {
        $thresholds = [7, 10, 7, 14, 10, 14, 7, 14, 10, 14];

        $warehouseItems = [
            1 => [1, 3, 4, 6, 7, 8, 10],
            2 => [2, 4, 5, 6, 9, 10],
            3 => [1, 2, 3, 5, 7, 8],
            4 => [4, 9],
        ];

        foreach ($warehouseItems as $warehouseId => $itemIds) {
            foreach ($itemIds as $itemId) {
                StockLevel::create([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity_on_hand' => -1,
                    'quantity_reserved' => 0,
                    'reorder_threshold' => $thresholds[$itemId - 1],
                ]);
            }
        }

        $movements = [
            // Day 14: Dasma Main receiving
            ['type' => 'inbound', 'item_id' => 1,  'warehouse_id' => 1, 'quantity' => 50,  'reference' => 'PO-2026-001', 'days_ago' => 14],
            ['type' => 'inbound', 'item_id' => 3,  'warehouse_id' => 1, 'quantity' => 35,  'reference' => 'PO-2026-001', 'days_ago' => 14],
            ['type' => 'inbound', 'item_id' => 4,  'warehouse_id' => 1, 'quantity' => 100, 'reference' => 'PO-2026-002', 'days_ago' => 14],
            ['type' => 'inbound', 'item_id' => 6,  'warehouse_id' => 1, 'quantity' => 80,  'reference' => 'PO-2026-002', 'days_ago' => 14],
            ['type' => 'inbound', 'item_id' => 7,  'warehouse_id' => 1, 'quantity' => 65,  'reference' => 'PO-2026-003', 'days_ago' => 14],
            ['type' => 'inbound', 'item_id' => 8,  'warehouse_id' => 1, 'quantity' => 30,  'reference' => 'PO-2026-003', 'days_ago' => 14],
            ['type' => 'inbound', 'item_id' => 10, 'warehouse_id' => 1, 'quantity' => 180, 'reference' => 'PO-2026-004', 'days_ago' => 14],

            // Day 13: Manila Hub receiving
            ['type' => 'inbound', 'item_id' => 2,  'warehouse_id' => 2, 'quantity' => 20,  'reference' => 'PO-2026-005', 'days_ago' => 13],
            ['type' => 'inbound', 'item_id' => 4,  'warehouse_id' => 2, 'quantity' => 80,  'reference' => 'PO-2026-005', 'days_ago' => 13],
            ['type' => 'inbound', 'item_id' => 5,  'warehouse_id' => 2, 'quantity' => 25,  'reference' => 'PO-2026-006', 'days_ago' => 13],
            ['type' => 'inbound', 'item_id' => 6,  'warehouse_id' => 2, 'quantity' => 33,  'reference' => 'PO-2026-006', 'days_ago' => 13],
            ['type' => 'inbound', 'item_id' => 9,  'warehouse_id' => 2, 'quantity' => 15,  'reference' => 'PO-2026-007', 'days_ago' => 13],
            ['type' => 'inbound', 'item_id' => 10, 'warehouse_id' => 2, 'quantity' => 50,  'reference' => 'PO-2026-007', 'days_ago' => 13],

            // Day 12: Laguna South receiving
            ['type' => 'inbound', 'item_id' => 1,  'warehouse_id' => 3, 'quantity' => 40,  'reference' => 'PO-2026-008', 'days_ago' => 12],
            ['type' => 'inbound', 'item_id' => 2,  'warehouse_id' => 3, 'quantity' => 18,  'reference' => 'PO-2026-008', 'days_ago' => 12],
            ['type' => 'inbound', 'item_id' => 3,  'warehouse_id' => 3, 'quantity' => 20,  'reference' => 'PO-2026-009', 'days_ago' => 12],
            ['type' => 'inbound', 'item_id' => 5,  'warehouse_id' => 3, 'quantity' => 28,  'reference' => 'PO-2026-009', 'days_ago' => 12],
            ['type' => 'inbound', 'item_id' => 7,  'warehouse_id' => 3, 'quantity' => 40,  'reference' => 'PO-2026-010', 'days_ago' => 12],
            ['type' => 'inbound', 'item_id' => 8,  'warehouse_id' => 3, 'quantity' => 25,  'reference' => 'PO-2026-010', 'days_ago' => 12],

            // Day 11: Clark North receiving
            ['type' => 'inbound', 'item_id' => 4,  'warehouse_id' => 4, 'quantity' => 15,  'reference' => 'PO-2026-011', 'days_ago' => 11],
            ['type' => 'inbound', 'item_id' => 9,  'warehouse_id' => 4, 'quantity' => 8,   'reference' => 'PO-2026-011', 'days_ago' => 11],

            // Day 9: Sales
            ['type' => 'outbound', 'item_id' => 6,  'warehouse_id' => 1, 'quantity' => 25,  'reference' => 'SO-2026-101', 'days_ago' => 9],
            ['type' => 'outbound', 'item_id' => 4,  'warehouse_id' => 2, 'quantity' => 40,  'reference' => 'SO-2026-102', 'days_ago' => 9],

            // Day 7: Sales
            ['type' => 'outbound', 'item_id' => 10, 'warehouse_id' => 1, 'quantity' => 60,  'reference' => 'SO-2026-103', 'days_ago' => 7],
            ['type' => 'outbound', 'item_id' => 2,  'warehouse_id' => 2, 'quantity' => 12,  'reference' => 'SO-2026-104', 'days_ago' => 7],
            ['type' => 'outbound', 'item_id' => 2,  'warehouse_id' => 3, 'quantity' => 8,   'reference' => 'SO-2026-105', 'days_ago' => 7],

            // Day 5: Sales
            ['type' => 'outbound', 'item_id' => 1,  'warehouse_id' => 1, 'quantity' => 10,  'reference' => 'SO-2026-106', 'days_ago' => 5],
            ['type' => 'outbound', 'item_id' => 5,  'warehouse_id' => 2, 'quantity' => 15,  'reference' => 'SO-2026-107', 'days_ago' => 5],
            ['type' => 'outbound', 'item_id' => 9,  'warehouse_id' => 2, 'quantity' => 10,  'reference' => 'SO-2026-108', 'days_ago' => 5],

            // Day 4: Sales
            ['type' => 'outbound', 'item_id' => 3,  'warehouse_id' => 1, 'quantity' => 5,   'reference' => 'SO-2026-109', 'days_ago' => 4],
            ['type' => 'outbound', 'item_id' => 7,  'warehouse_id' => 1, 'quantity' => 5,   'reference' => 'SO-2026-110', 'days_ago' => 4],
            ['type' => 'outbound', 'item_id' => 4,  'warehouse_id' => 4, 'quantity' => 5,   'reference' => 'SO-2026-111', 'days_ago' => 4],

            // Day 3: Sales
            ['type' => 'outbound', 'item_id' => 1,  'warehouse_id' => 3, 'quantity' => 10,  'reference' => 'SO-2026-112', 'days_ago' => 3],
            ['type' => 'outbound', 'item_id' => 5,  'warehouse_id' => 3, 'quantity' => 12,  'reference' => 'SO-2026-113', 'days_ago' => 3],
            ['type' => 'outbound', 'item_id' => 4,  'warehouse_id' => 1, 'quantity' => 20,  'reference' => 'SO-2026-114', 'days_ago' => 3],

            // Day 2: Sales
            ['type' => 'outbound', 'item_id' => 8,  'warehouse_id' => 1, 'quantity' => 5,   'reference' => 'SO-2026-115', 'days_ago' => 2],
            ['type' => 'outbound', 'item_id' => 6,  'warehouse_id' => 2, 'quantity' => 5,   'reference' => 'SO-2026-116', 'days_ago' => 2],
            ['type' => 'outbound', 'item_id' => 7,  'warehouse_id' => 3, 'quantity' => 5,   'reference' => 'SO-2026-117', 'days_ago' => 2],

            // Day 1: Sales
            ['type' => 'outbound', 'item_id' => 9,  'warehouse_id' => 4, 'quantity' => 8,   'reference' => 'SO-2026-118', 'days_ago' => 1],
            ['type' => 'outbound', 'item_id' => 8,  'warehouse_id' => 3, 'quantity' => 7,   'reference' => 'SO-2026-119', 'days_ago' => 1],
        ];

        foreach ($movements as $m) {
            StockMovement::create([
                'type' => $m['type'],
                'item_id' => $m['item_id'],
                'warehouse_id' => $m['warehouse_id'],
                'quantity' => $m['quantity'],
                'reference' => $m['reference'],
                'created_at' => Carbon::now()->subDays($m['days_ago']),
            ]);
        }

        $approvedAdjustments = [
            ['item_id' => 1, 'warehouse_id' => 1, 'type' => 'increase', 'quantity' => 5,  'reason' => 'recount',    'days_ago' => 6],
            ['item_id' => 6, 'warehouse_id' => 2, 'type' => 'decrease', 'quantity' => 3,  'reason' => 'damage',     'days_ago' => 6],
            ['item_id' => 5, 'warehouse_id' => 3, 'type' => 'increase', 'quantity' => 2,  'reason' => 'correction', 'days_ago' => 6],
        ];

        foreach ($approvedAdjustments as $adj) {
            $adjustment = StockAdjustment::create([
                'item_id' => $adj['item_id'],
                'warehouse_id' => $adj['warehouse_id'],
                'type' => $adj['type'],
                'quantity' => $adj['quantity'],
                'reason' => $adj['reason'],
                'status' => 'approved',
                'approved_at' => Carbon::now()->subDays($adj['days_ago']),
                'created_at' => Carbon::now()->subDays($adj['days_ago']),
                'updated_at' => Carbon::now()->subDays($adj['days_ago']),
            ]);

            StockMovement::create([
                'type' => 'adjustment',
                'item_id' => $adj['item_id'],
                'warehouse_id' => $adj['warehouse_id'],
                'quantity' => $adj['quantity'],
                'reference' => 'ADJ-' . str_pad($adjustment->id, 6, '0', STR_PAD_LEFT),
                'notes' => "Adjustment #{$adjustment->id} approved: {$adj['type']} ({$adj['reason']})",
                'created_at' => Carbon::now()->subDays($adj['days_ago']),
            ]);
        }

        $pendingAdjustments = [
            ['item_id' => 8,  'warehouse_id' => 4, 'type' => 'decrease', 'quantity' => 4,  'reason' => 'expired'],
            ['item_id' => 10, 'warehouse_id' => 1, 'type' => 'increase', 'quantity' => 15, 'reason' => 'recount'],
        ];

        foreach ($pendingAdjustments as $adj) {
            StockAdjustment::create([
                'item_id' => $adj['item_id'],
                'warehouse_id' => $adj['warehouse_id'],
                'type' => $adj['type'],
                'quantity' => $adj['quantity'],
                'reason' => $adj['reason'],
                'status' => 'pending',
            ]);
        }

        $quantities = [];

        foreach ($movements as $m) {
            $key = "{$m['warehouse_id']}-{$m['item_id']}";
            if (!isset($quantities[$key])) {
                $quantities[$key] = ['item_id' => $m['item_id'], 'warehouse_id' => $m['warehouse_id'], 'qty' => 0];
            }
            if ($m['type'] === 'inbound') {
                $quantities[$key]['qty'] += $m['quantity'];
            } elseif ($m['type'] === 'outbound') {
                $quantities[$key]['qty'] -= $m['quantity'];
            }
        }

        foreach ($approvedAdjustments as $adj) {
            $key = "{$adj['warehouse_id']}-{$adj['item_id']}";
            if (!isset($quantities[$key])) {
                $quantities[$key] = ['item_id' => $adj['item_id'], 'warehouse_id' => $adj['warehouse_id'], 'qty' => 0];
            }
            if ($adj['type'] === 'increase') {
                $quantities[$key]['qty'] += $adj['quantity'];
            } else {
                $quantities[$key]['qty'] -= $adj['quantity'];
            }
        }

        foreach ($quantities as $data) {
            $stockLevel = StockLevel::where('item_id', $data['item_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            $qty = max(0, $data['qty']);
            $stockLevel->update([
                'quantity_on_hand' => $qty,
                'quantity_reserved' => (int) round($qty * 0.08),
            ]);
        }
    }
}
