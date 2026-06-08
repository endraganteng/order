<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'po_number',
        'supplier_name',
        'supplier_id',
        'supplier_phone',
        'status',
        'notes',
        'created_by',
        'created_by_name',
        'items_count',
        'received_count',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function scopeByStatus($query, ?string $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }
}
