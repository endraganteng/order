<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchDestroyRecurringTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'required|string',
            'redirect_scope' => 'nullable|string|in:rack_check,general',
        ];
    }
}
