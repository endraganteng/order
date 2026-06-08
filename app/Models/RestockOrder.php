<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockOrder extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'source_task_id',
        'source_task_legacy_key',
        'rack_id',
        'rack_name',
        'product_id',
        'product_name',
        'unit',
        'standard_qty',
        'actual_qty',
        'needed_qty',
        'fulfilled_qty',
        'status',
        'priority',
        'assigned_to',
        'assigned_to_name',
        'fulfilled_by',
        'fulfilled_by_name',
        'fulfilled_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];
}
