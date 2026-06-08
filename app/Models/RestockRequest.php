<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockRequest extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'product_id',
        'product_name',
        'product_category_id',
        'rack_id',
        'rack_name',
        'reported_qty',
        'standard_qty',
        'qty_needed',
        'status',
        'source',
        'reported_by',
        'reported_by_name',
        'date',
        'note',
        'po_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'in_po']);
    }
}
