<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'name',
        'description',
        'sort_order',
        'is_active',
        'event_created_at',
        'event_updated_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'event_created_at' => 'integer',
        'event_updated_at' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
