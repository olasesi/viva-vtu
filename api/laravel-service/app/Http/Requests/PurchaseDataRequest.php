<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => 'required|string|min:11|max:11',
            'amount' => 'required|numeric|min:50',
            'network' => 'required|string|in:mtn,glo,9mobile,airtel',
            'plan' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.required' => 'The phone number is required.',
            'phone_number.min' => 'Phone number must be 11 digits.',
            'phone_number.max' => 'Phone number must be 11 digits.',
            'amount.required' => 'The amount is required.',
            'amount.min' => 'Minimum data purchase is ₦50.',
            'network.required' => 'The network provider is required.',
            'network.in' => 'Invalid network provider.',
            'plan.required' => 'The data plan is required.',
        ];
    }
}
