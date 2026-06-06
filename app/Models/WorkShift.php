<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'name',
        'clock_in_time',
        'clock_out_time',
        'late_tolerance_minutes',
        'is_active',
        'retail_tag',
        'event_created_at',
        'event_updated_at',
    ];

    protected $casts = [
        'late_tolerance_minutes' => 'integer',
        'is_active' => 'boolean',
        'event_created_at' => 'integer',
        'event_updated_at' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
