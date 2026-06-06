<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'action',
        'entity',
        'entity_id',
        'admin_id',
        'admin_name',
        'details',
        'ip',
        'event_timestamp',
        'event_date',
    ];

    protected $casts = [
        'details' => 'array',
        'event_timestamp' => 'integer',
        'event_date' => 'date',
    ];

    public function scopeForDate($query, $date)
    {
        return $query->where('event_date', $date);
    }

    public function scopeForEntity($query, string $entity)
    {
        return $query->where('entity', $entity);
    }
}
