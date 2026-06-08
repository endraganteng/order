<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationReport extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'iso_year_week',
        'status',
        'total_products',
        'anomaly_count',
        'drift_avg_pct',
        'anomalies',
        'summary',
    ];

    protected $casts = [
        'anomalies' => 'array',
        'summary' => 'array',
    ];

    public function scopeForWeek($query, string $week)
    {
        return $query->where('iso_year_week', $week);
    }
}
