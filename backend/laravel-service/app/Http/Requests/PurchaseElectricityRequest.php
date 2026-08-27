<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseElectricityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:500',
            'meter_number' => 'required|string|min:10|max:15',
            'meter_type' => 'required|string|in:prepaid,postpaid',
            'disco' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.min' => 'Minimum electricity purchase is ₦500.',
            'meter_number.required' => 'The meter number is required.',
            'meter_number.min' => 'Meter number must be at least 10 digits.',
            'meter_type.required' => 'The meter type is required.',
            'meter_type.in' => 'Meter type must be either prepaid or postpaid.',
            'disco.required' => 'The electricity distribution company is required.',
        ];
    }
}
