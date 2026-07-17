<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Procurement extends Model
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

    public function getSupplierProduct(): ?object
    {
        return DB::connection('procurement')
            ->table('purchase_order_items')
            ->join('supplier_products', 'purchase_order_items.supplier_product_id', '=', 'supplier_products.id')
            ->where('purchase_order_items.purchase_order_id', $this->purchase_order_id)
            ->select(
                'purchase_order_items.name as item_name',
                'purchase_order_items.qty',
                'purchase_order_items.unit_price',
                'supplier_products.sku'
            )
            ->first();
    }
}
