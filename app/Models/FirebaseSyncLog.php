<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirebaseSyncLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'firebase_path',
        'action',
        'status',
        'payload',
        'error_message',
        'attempt_count',
        'last_attempt_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->where('next_retry_at', '<=', now());
    }
}
