<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProductEnrichmentBatch extends Model
{
    protected $fillable = [
        'mode',
        'status',
        'total_items',
        'processed_items',
        'success_count',
        'failed_count',
        'skipped_count',
        'auto_approve',
        'auto_sync',
        'current_product_id',
        'current_product_name',
        'last_message',
        'options',
        'summary',
        'initiated_by',
        'pid',
        'started_at',
        'finished_at',
        'heartbeat_at',
        'log_file',
        'spawn_error',
        'artisan_command',
    ];

    protected function casts(): array
    {
        return [
            'auto_approve' => 'bool',
            'auto_sync' => 'bool',
            'options' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'total_items' => 'int',
            'processed_items' => 'int',
            'success_count' => 'int',
            'failed_count' => 'int',
            'skipped_count' => 'int',
        ];
    }

    public function progressPct(): float
    {
        if ($this->total_items <= 0) {
            return 0.0;
        }

        return round(min(100, ($this->processed_items / $this->total_items) * 100), 1);
    }

    public function isStale(int $thresholdSeconds = 90): bool
    {
        if (! $this->heartbeat_at) {
            return false;
        }

        return $this->heartbeat_at->diffInSeconds(now()) > $thresholdSeconds;
    }
}
