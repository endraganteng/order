<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterActivityReport extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'waiter_id',
        'waiter_name',
        'waiter_email',
        'report_date',
        'activity_text',
        'activity_items',
        'event_timestamp',
    ];

    protected $casts = [
        'report_date' => 'date',
        'activity_items' => 'array',
        'event_timestamp' => 'integer',
    ];

    public function scopeForWaiter($query, string $waiterId)
    {
        return $query->where('waiter_id', $waiterId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('report_date', $date);
    }
}
