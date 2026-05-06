<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_timeout_minutes' => 'required|integer|min:1',
            'fonnte_api_token' => 'nullable|string|max:500',
            'fonnte_enabled' => 'nullable|boolean',
            'report_phone' => 'nullable|string|max:20',
            'auto_report_enabled' => 'nullable|boolean',
        ];
    }
}
