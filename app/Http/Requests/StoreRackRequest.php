<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'location' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'rack_type' => 'required|in:display,storage',
            'check_order' => 'nullable|integer|min:0|max:999',
        ];
    }
}
