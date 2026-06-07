<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashierTask extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'deterministic_key',
        'template_id',
        'source_template_key',
        'title',
        'description',
        'assigned_cashier_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'is_recurring',
        'recurrence_pattern',
        'metadata',
        'firebase_payload',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_recurring' => 'boolean',
        'metadata' => 'array',
        'firebase_payload' => 'array',
        'completed_at' => 'datetime',
    ];

    public function scopeForDate($query, $date)
    {
        return $query->where('scheduled_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }
}
