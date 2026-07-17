<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\StockLevel;

class StockLevelObserver
{
    public function updated(StockLevel $stockLevel): void
    {
        if (!$stockLevel->wasChanged('stock') && !$stockLevel->wasChanged('reorder_threshold')) {
            return;
        }

        $onHand = $stockLevel->stock;

        if ($onHand <= 0) {
            $this->createOrUpdateNotification($stockLevel, 'out_of_stock');
        } elseif ($stockLevel->status === 'low_stock') {
            $this->createOrUpdateNotification($stockLevel, 'low_stock');
        } else {
            $this->autoResolveNotification($stockLevel);
        }
    }

    private function createOrUpdateNotification(StockLevel $stockLevel, string $type): void
    {
        $existing = Notification::where('item_id', $stockLevel->item_id)
            ->where('warehouse_id', $stockLevel->warehouse_id)
            ->where('status', '!=', 'resolved')
            ->first();

        if ($existing) {
            $existing->update(['type' => $type]);
            return;
        }

        Notification::create([
            'item_id' => $stockLevel->item_id,
            'warehouse_id' => $stockLevel->warehouse_id,
            'type' => $type,
            'triggered_by' => $stockLevel->notification_source,
            'status' => 'open',
        ]);
    }

    private function autoResolveNotification(StockLevel $stockLevel): void
    {
        Notification::where('item_id', $stockLevel->item_id)
            ->where('warehouse_id', $stockLevel->warehouse_id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
