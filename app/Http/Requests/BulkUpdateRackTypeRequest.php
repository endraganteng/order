<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRackTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rack_ids' => 'required|array|min:1',
            'rack_ids.*' => 'required|string',
            'rack_type' => 'required|in:display,storage',
        ];
    }
}
