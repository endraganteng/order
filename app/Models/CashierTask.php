<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashierTask extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'deterministic_key',
        'template_id',
        'title',
        'description',
        'assigned_cashier_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'is_recurring',
        'recurrence_pattern',
        'metadata',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_recurring' => 'boolean',
        'metadata' => 'array',
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
