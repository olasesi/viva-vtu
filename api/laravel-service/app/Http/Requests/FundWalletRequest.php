<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FundWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:100|max:500000',
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The funding amount is required.',
            'amount.min' => 'The minimum funding amount is ₦100.',
            'amount.max' => 'The maximum funding amount is ₦500,000.',
            'email.required' => 'Your email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
