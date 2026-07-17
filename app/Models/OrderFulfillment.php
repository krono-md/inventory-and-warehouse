<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFulfillment extends Model
{
    protected $table = 'packing_materials';

    protected $fillable = [
        'name',
        'stock_qty',
        'low_stock_threshold',
        'is_box',
        'box_size',
    ];

    protected $casts = [
        'is_box' => 'boolean',
    ];
}
