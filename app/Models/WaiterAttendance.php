<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterAttendance extends Model
{
    protected $fillable = [
        'waiter_id',
        'date',
        'status',
        'late_minutes',
        'clock_in',
        'clock_out',
        'data',
    ];

    protected $casts = [
        'date' => 'date',
        'late_minutes' => 'integer',
        'data' => 'array',
    ];

    public function scopeForWaiter($query, string $waiterId)
    {
        return $query->where('waiter_id', $waiterId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }
}
