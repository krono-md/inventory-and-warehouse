<?php

namespace App\Models;

use App\Observers\StockLevelObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(StockLevelObserver::class)]
class StockLevel extends Model
{
    protected $fillable = [
        'item_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_threshold',
    ];

    public string $notification_source = 'system';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    public function getStatusAttribute(): string
    {
        if ($this->quantity_on_hand <= 0) {
            return 'out_of_stock';
        }

        if ($this->reorder_threshold > 0 && $this->quantity_on_hand <= $this->reorder_threshold) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            default => 'In Stock',
        };
    }
}
