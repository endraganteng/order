<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRackCheckTemplateRequest extends FormRequest
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
            'template_name' => 'nullable|string|max:100',
            'recurrence_type' => ['required', Rule::in(['daily', 'weekly', 'every_n_days'])],
            'weekly_day' => 'nullable|integer|min:1|max:7',
            'interval_days' => 'nullable|integer|min:1|max:365',
            'recurrence_anchor_date' => 'required|date_format:Y-m-d',
            'requires_barcode_scan' => 'nullable|boolean',
            'requires_photo_before' => 'nullable|boolean',
            'requires_photo_proof' => 'nullable|boolean',
            'allow_note' => 'nullable|boolean',
            'enable_empty_product_report' => 'nullable|boolean',
            'full_shift_daily_cap' => 'nullable|integer|min:0|max:99',
            'partial_shift_daily_cap' => 'nullable|integer|min:0|max:99',
        ];
    }

    public function messages(): array
    {
        return [
            'recurrence_type.required' => 'Pilih pola pengulangan.',
            'recurrence_anchor_date.required' => 'Tanggal mulai wajib diisi.',
            'recurrence_anchor_date.date_format' => 'Format tanggal mulai harus YYYY-MM-DD.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $type = $this->input('recurrence_type');
            if ($type === 'weekly' && ! $this->filled('weekly_day')) {
                $v->errors()->add('weekly_day', 'Hari mingguan wajib dipilih untuk mode mingguan.');
            }
            if ($type === 'every_n_days' && ! $this->filled('interval_days')) {
                $v->errors()->add('interval_days', 'Interval hari wajib diisi untuk mode setiap N hari.');
            }
        });
    }
}
