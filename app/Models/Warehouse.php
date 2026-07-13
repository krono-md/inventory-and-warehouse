<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'province',
        'city',
        'barangay',
        'address_description',
        'country',
        'capacity_units',
        'status',
    ];

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getUsedUnitsAttribute(): int
    {
        return $this->stockLevels()->sum('quantity_on_hand');
    }

    public function getCapacityPercentageAttribute(): int
    {
        if ($this->capacity_units === 0) {
            return 0;
        }

        return (int) round(($this->used_units / $this->capacity_units) * 100);
    }
}
