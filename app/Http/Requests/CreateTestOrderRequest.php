<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'waiter_name' => 'required|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string',
            'products.*.price' => 'required|numeric|min:0',
        ];
    }
}
