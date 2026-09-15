<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = app(SettingService::class);
        $group = $this->route('group');

        $rules = [
            'fields' => ['required', 'array'],
        ];

        foreach ($service->fields($group) as $key => $field) {
            $ruleString = $field['rules'] ?? '';

            if (str_contains($ruleString, 'required')) {
                $ruleString = str_replace('required', 'sometimes', $ruleString);
            } else {
                $ruleString = 'sometimes|'.$ruleString;
            }

            $rules["fields.{$key}"] = explode('|', $ruleString);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'fields.required' => 'The fields payload is required.',
            'fields.array' => 'The fields payload must be an object.',
        ];
    }

    protected function passedValidation(): void
    {
        $service = app(SettingService::class);
        $group = $this->route('group');

        $allowed = array_keys($service->fields($group));
        $unknown = array_diff(array_keys($this->input('fields', [])), $allowed);

        if (! empty($unknown)) {
            throw ValidationException::withMessages([
                'fields.'.head($unknown) => 'The selected field is invalid.',
            ]);
        }
    }
}
