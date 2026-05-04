<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRackRequest extends FormRequest
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
            'is_active' => 'required|boolean',
        ];
    }
}
