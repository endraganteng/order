<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RackProduct extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'name',
        'category_id',
        'standard_qty',
        'unit',
        'is_active',
        'firebase_payload',
        'event_created_at',
        'event_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'standard_qty' => 'integer',
        'firebase_payload' => 'array',
        'event_created_at' => 'integer',
        'event_updated_at' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
