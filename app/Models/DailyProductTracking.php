<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyProductTracking extends Model
{
    protected $fillable = [
        'tracking_date',
        'product_id',
        'product_name',
        'stok_masuk_qty',
        'stok_masuk_total',
        'sisa_stok_qty',
        'penjualan_nominal',
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'stok_masuk_qty' => 'decimal:2',
        'stok_masuk_total' => 'decimal:2',
        'sisa_stok_qty' => 'decimal:2',
        'penjualan_nominal' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    public function scopeForDate($query, $date)
    {
        return $query->where('tracking_date', $date);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tracking_date', [$startDate, $endDate]);
    }
}
