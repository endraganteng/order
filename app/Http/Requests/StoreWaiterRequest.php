<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaiterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'waiter_role' => 'required|in:kasir,pelayan,supervisor',
            'password' => 'nullable|string|min:6|max:100',
            'shift_id' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ];
    }
}
