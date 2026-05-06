<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority' => 'required|in:urgent,normal,low',
            'category_id' => 'nullable|string|max:100',
            'category_name' => 'nullable|string|max:100',
            'schedule_time' => 'required|date_format:H:i',
            'time_limit_minutes' => 'required|integer|min:1|max:1440',
            'is_active' => 'nullable|boolean',
            'recurrence_type' => 'required|in:daily,weekly,every_n_days',
            'weekly_day' => 'nullable|integer|min:1|max:7',
            'interval_days' => 'nullable|integer|min:1|max:365',
            'reset_anchor_date' => 'nullable|boolean',
        ];
    }
}
