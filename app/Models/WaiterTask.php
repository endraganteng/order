<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterTask extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'firebase_active_path',
        'deterministic_key',
        'template_id',
        'template_legacy_key',
        'task_type',
        'title',
        'description',
        'assigned_waiter_id',
        'assigned_waiter_name',
        'scheduled_for_date',
        'scheduled_time',
        'status',
        'publish_status',
        'sync_status',
        'priority',
        'rack_id',
        'rack_code',
        'rack_name',
        'started_at',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'ignored_at',
        'ignored_by',
        'ignore_reason',
        'photo_url',
        'notes',
        'metadata',
        'firebase_payload',
        'sync_error',
        'synced_at',
        'created_by',
    ];

    protected $casts = [
        'scheduled_for_date' => 'date',
        'metadata' => 'array',
        'firebase_payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ignored_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function scopeForWaiter($query, string $waiterId)
    {
        return $query->where('assigned_waiter_id', $waiterId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('scheduled_for_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    public function scopePendingSync($query)
    {
        return $query->where('sync_status', 'pending');
    }
}
