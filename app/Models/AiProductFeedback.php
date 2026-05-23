<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProductFeedback extends Model
{
    protected $fillable = [
        'session_id',
        'message_id',
        'user_id',
        'user_type',
        'question',
        'answer',
        'product_ids',
        'rating',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
        ];
    }
}
