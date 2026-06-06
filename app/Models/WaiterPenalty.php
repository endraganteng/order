<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterPenalty extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'waiter_id',
        'waiter_name',
        'penalty_type',
        'penalty_label',
        'points_deducted',
        'date',
        'month',
        'reason',
        'evidence_photo_url',
        'related_task_id',
        'event_created_at',
    ];

    protected $casts = [
        'points_deducted' => 'integer',
        'date' => 'date',
        'event_created_at' => 'integer',
    ];

    public function scopeForWaiter($query, string $waiterId)
    {
        return $query->where('waiter_id', $waiterId);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('month', $month);
    }
}
