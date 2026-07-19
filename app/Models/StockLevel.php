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
        'stock',
        'reserved_quantity',
        'reorder_threshold',
    ];

    protected $casts = [
        'reserved_quantity' => 'integer',
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

    public function getAvailableQuantityAttribute(): int
    {
        return $this->stock - $this->reserved_quantity;
    }

    public function getStatusAttribute(): string
    {
        $available = $this->available_quantity;

        if ($available <= 0) {
            return 'out_of_stock';
        }

        if ($this->reorder_threshold > 0 && $available <= $this->reorder_threshold) {
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

    public function scopeInStock($query)
    {
        return $query->whereColumn('stock', '>', 'reserved_quantity');
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock > reserved_quantity')
            ->whereRaw('stock - reserved_quantity <= reorder_threshold')
            ->where('reorder_threshold', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereColumn('stock', '<=', 'reserved_quantity');
    }
}
