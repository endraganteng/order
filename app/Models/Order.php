<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'firebase_legacy_key',
        'queue_number',
        'waiter_id',
        'waiter_name',
        'waiter_email',
        'products',
        'product_count',
        'total_price',
        'status',
        'expires_at',
        'order_date',
    ];

    protected $casts = [
        'products' => 'array',
        'expires_at' => 'datetime',
        'order_date' => 'date',
    ];

    public function scopeForDate($query, string $date)
    {
        return $query->where('order_date', $date);
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('order_date', [$from, $to]);
    }
}
