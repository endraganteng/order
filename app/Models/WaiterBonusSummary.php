<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterBonusSummary extends Model
{
    protected $fillable = [
        'waiter_id',
        'period_key',
        'status',
        'finalized_at',
        'summary',
    ];

    protected $casts = [
        'finalized_at' => 'integer',
        'summary' => 'array',
    ];

    public function scopeForPeriod($query, string $periodKey)
    {
        return $query->where('period_key', $periodKey);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }
}
