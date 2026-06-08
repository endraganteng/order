<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'restock_id',
        'product_id',
        'product_name',
        'product_category_id',
        'rack_id',
        'rack_name',
        'qty_needed',
        'qty_ordered',
        'qty_received',
        'status',
        'note',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
