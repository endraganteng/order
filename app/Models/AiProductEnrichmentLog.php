<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProductEnrichmentLog extends Model
{
    protected $fillable = [
        'job_id',
        'product_id',
        'action',
        'message',
        'metadata',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AiProductEnrichmentJob::class, 'job_id');
    }
}
