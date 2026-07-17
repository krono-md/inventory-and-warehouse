<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $connection = 'procurement';
    protected $table = 'deliveries';

    protected $fillable = [
        'shipment_number',
        'purchase_order_id',
        'supplier_id',
        'stage',
        'status',
        'qty',
        'qty_expected',
        'items',
        'started_at',
        'remarks',
        'delivery_date',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'started_at' => 'datetime',
    ];
}
