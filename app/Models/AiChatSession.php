<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatSession extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'title',
        'summary',
        'summarized_up_to',
        'last_product_ids',
        'primary_product_id',
    ];

    protected function casts(): array
    {
        return [
            'last_product_ids' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id');
    }
}
