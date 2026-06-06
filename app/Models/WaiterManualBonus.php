<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterManualBonus extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'waiter_id',
        'waiter_name',
        'month',
        'date',
        'points',
        'reason',
        'category',
        'created_by',
        'event_created_at',
    ];

    protected $casts = [
        'points' => 'integer',
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
