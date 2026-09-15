<?php

use App\Services\SettingService;

beforeEach(function () {
    $this->service = new SettingService;
});

it('lists all available groups', function () {
    $groups = $this->service->groups();

    expect($groups)->toBeArray()
        ->and($groups)->toHaveCount(17)
        ->and($groups)->toContain('business')
        ->and($groups)->toContain('sms');
});

it('checks whether a group exists', function () {
    expect($this->service->hasGroup('business'))->toBeTrue()
        ->and($this->service->hasGroup('not-a-real-group'))->toBeFalse();
});

it('returns all fields for a group', function () {
    $fields = $this->service->fields('email');

    expect($fields)->toHaveKeys([
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ]);
});

it('returns a default value for a given field', function () {
    expect($this->service->default('dashboard', 'default_datatable_page_entries'))->toBe(25)
        ->and($this->service->default('display_screen', 'enable_customer_display'))->toBeFalse();
});

it('extracts validation rules for a group from field definitions', function () {
    $rules = $this->service->rules('email');

    expect($rules['mail_host'])->toContain('required')
        ->and($rules['mail_port'])->toContain('integer');
});

it('returns the raw definition for a field', function () {
    $field = $this->service->definition('sms', 'request_method');

    expect($field)->toHaveKey('default')
        ->and($field['default'])->toBe('POST');
});

it('knows which fields within a group are sensitive', function () {
    expect($this->service->sensitiveFields('email'))->toBe(['mail_password'])
        ->and($this->service->sensitiveFields('payment'))->toContain('paystack_secret_key')
        ->and($this->service->sensitiveFields('business'))->toBe([]);
});
