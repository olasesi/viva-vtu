<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseCableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:500',
            'smartcard_number' => 'required|string|min:10',
            'cable' => 'required|string|in:dstv,gotv,startimes',
            'package' => 'required|string',
            'action' => 'required|string|in:validate,subscribe',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.min' => 'Minimum cable subscription is ₦500.',
            'smartcard_number.required' => 'The smartcard number is required.',
            'cable.required' => 'The cable provider is required.',
            'cable.in' => 'Invalid cable provider. Choose from: dstv, gotv, startimes.',
            'package.required' => 'The cable package is required.',
            'action.required' => 'The action type is required.',
            'action.in' => 'Action must be either validate or subscribe.',
        ];
    }
}
