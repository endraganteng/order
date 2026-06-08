<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RackCheckPlan extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'template_id',
        'template_legacy_key',
        'plan_date',
        'plan_period',
        'waiter_id',
        'waiter_name',
        'rack_id',
        'rack_name',
        'rack_location',
        'status',
        'skip_reason',
        'assigned_by',
        'metadata',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'metadata' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaiterTaskTemplate::class, 'template_id');
    }
}
