<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:urgent,normal,low',
            'task_scope' => 'required|in:general,rack_check',
            'task_type' => 'required|in:general,rack_check',
            'category_id' => 'nullable|string|max:100',
            'category_name' => 'nullable|string|max:100',
            'requires_photo_proof' => 'nullable|boolean',
            'rack_target_scope' => 'nullable|in:single,all',
            'rack_id' => 'nullable|string',
            'rack_ids' => 'nullable|array',
            'rack_ids.*' => 'nullable|string',
            'assignment_type' => 'required_without:batch_tasks_json|in:single,all,role',
            'assigned_waiter_id' => 'nullable|string',
            'assigned_waiter_role' => 'nullable|in:kasir,pelayan,backup,supervisor,finance',
            'role_assignment_mode' => 'nullable|in:all,rolling,selected',
            'selected_waiter_ids' => 'nullable|array',
            'selected_waiter_ids.*' => 'nullable|string',
            'fixed_rack_assignments' => 'nullable|string',
            'batch_tasks_json' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'schedule_time' => 'nullable|date_format:H:i',
            'time_limit_minutes' => 'nullable|integer|min:0|max:1440',
            'schedule_mode' => 'nullable|in:fixed,shift_relative',
            'shift_offset_minutes' => 'nullable|integer|min:0|max:480',
            'deadline_mode' => 'nullable|in:fixed,before_shift_end',
            'deadline_before_end_minutes' => 'nullable|integer|min:0|max:480',
            'recurrence_type' => 'nullable|in:daily,weekly,every_n_days',
            'weekly_day' => 'nullable|integer|min:1|max:7',
            'interval_days' => 'nullable|integer|min:1|max:365',
        ];
    }
}
