<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProductEnrichmentJob extends Model
{
    protected $fillable = [
        'product_id',
        'product_name',
        'base_name',
        'variant_label',
        'inherited_from_product_id',
        'is_inherited',
        'status',
        'source_count',
        'confidence_score',
        'generated_by',
        'approved_by',
        'approved_at',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_inherited' => 'boolean',
            'source_count' => 'integer',
            'confidence_score' => 'decimal:3',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AiProductEnrichmentLog::class, 'job_id');
    }
}
