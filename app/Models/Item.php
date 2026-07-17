<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'unit_cost',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereIn('items.id', function ($sq) {
            $sq->select('item_id')
                ->from('stock_levels')
                ->groupBy('item_id')
                ->havingRaw('SUM(quantity_on_hand) <= 0');
        });
    }

    public function scopeLowStock($query)
    {
        return $query->whereIn('items.id', function ($sq) {
            $sq->select('item_id')
                ->from('stock_levels')
                ->whereColumn('quantity_on_hand', '<=', 'reorder_threshold')
                ->where('quantity_on_hand', '>', 0)
                ->groupBy('item_id');
        })->whereIn('items.id', function ($sq) {
            $sq->select('item_id')
                ->from('stock_levels')
                ->groupBy('item_id')
                ->havingRaw('SUM(quantity_on_hand) > 0');
        });
    }

    public function scopeInStock($query)
    {
        return $query->whereIn('items.id', function ($sq) {
            $sq->select('item_id')
                ->from('stock_levels')
                ->groupBy('item_id')
                ->havingRaw('SUM(quantity_on_hand) > 0');
        })->whereNotIn('items.id', function ($sq) {
            $sq->select('item_id')
                ->from('stock_levels')
                ->whereColumn('quantity_on_hand', '<=', 'reorder_threshold')
                ->where('quantity_on_hand', '>', 0)
                ->groupBy('item_id');
        });
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity_on_hand');
    }

    public function getStatusAttribute(): string
    {
        $total = $this->total_stock;

        if ($total <= 0) {
            return 'Out of Stock';
        }

        $hasLowStock = $this->stockLevels->contains(fn ($sl) => $sl->status === 'low_stock');

        if ($hasLowStock) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}
