<?php

it('defines all required settings groups', function () {
    $groups = config('settings.groups');

    expect(array_keys($groups))->toBe([
        'business',
        'tax',
        'product',
        'contact',
        'sale',
        'pos',
        'display_screen',
        'purchases',
        'payment',
        'dashboard',
        'system',
        'prefixes',
        'email',
        'sms',
        'reward_points',
        'modules',
        'custom_labels',
    ]);
});

it('gives every group a label, icon and fields array', function () {
    foreach (config('settings.groups') as $slug => $group) {
        expect($group)
            ->toHaveKey('label')
            ->toHaveKey('icon')
            ->toHaveKey('fields');

        expect($group['fields'])->toBeArray();

        foreach ($group['fields'] as $key => $field) {
            expect($field)->toHaveKey('label')
                ->toHaveKey('type')
                ->toHaveKey('default')
                ->and($field['type'])->toBeIn(['string', 'boolean', 'integer', 'decimal', 'enum', 'array']);
        }
    }
});

it('validates field types are known cast types', function () {
    $known = ['string', 'boolean', 'integer', 'decimal', 'enum', 'array'];

    foreach (config('settings.groups') as $group) {
        foreach ($group['fields'] as $field) {
            expect(in_array($field['type'], $known, true))->toBeTrue()
                ->and($field['type'])->not->toBeNull();
        }
    }
});

it('marks email password and payment secrets as sensitive', function () {
    $email = config('settings.groups.email.fields');
    $payment = config('settings.groups.payment.fields');

    expect($email['mail_password']['sensitive'] ?? false)->toBeTrue();
    expect($payment['paystack_secret_key']['sensitive'] ?? false)->toBeTrue();
    expect($payment['flutterwave_secret_key']['sensitive'] ?? false)->toBeTrue();
});

it('defines the display screen fields from the specification', function () {
    $fields = config('settings.groups.display_screen.fields');

    expect(array_keys($fields))->toBe([
        'enable_customer_display',
        'heading_text',
        'carousel_image_1',
        'carousel_image_2',
        'carousel_image_3',
        'carousel_image_4',
        'carousel_image_5',
        'carousel_image_6',
        'carousel_image_7',
        'carousel_image_8',
        'carousel_image_9',
        'carousel_image_10',
    ]);

    expect($fields['enable_customer_display']['type'])->toBe('boolean')
        ->and($fields['enable_customer_display']['default'])->toBeFalse();
});

it('defines the email smtp fields from the specification', function () {
    $fields = config('settings.groups.email.fields');

    expect(array_keys($fields))->toBe([
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ]);

    expect($fields['mail_driver']['default'])->toBe('smtp')
        ->and($fields['mail_encryption']['type'])->toBe('enum')
        ->and($fields['mail_encryption']['options'])->toBe(['tls', 'ssl']);
});

it('defines the sms settings fields from the specification', function () {
    $fields = config('settings.groups.sms.fields');

    expect(array_keys($fields))->toBe([
        'sms_service',
        'url',
        'send_to_parameter',
        'message_parameter',
        'request_method',
        'data_parameter_type',
        'header_1_key',
        'header_1_value',
        'header_2_key',
        'header_2_value',
        'header_3_key',
        'header_3_value',
        'parameter_1_key',
        'parameter_1_value',
        'parameter_2_key',
        'parameter_2_value',
        'parameter_3_key',
        'parameter_3_value',
        'parameter_4_key',
        'parameter_4_value',
        'parameter_5_key',
        'parameter_5_value',
        'parameter_6_key',
        'parameter_6_value',
        'parameter_7_key',
        'parameter_7_value',
        'parameter_8_key',
        'parameter_8_value',
        'parameter_9_key',
        'parameter_9_value',
        'parameter_10_key',
        'parameter_10_value',
        'test_number',
    ]);

    expect($fields['sms_service']['default'])->toBe('other')
        ->and($fields['send_to_parameter']['default'])->toBe('to')
        ->and($fields['message_parameter']['default'])->toBe('text')
        ->and($fields['request_method']['default'])->toBe('POST')
        ->and($fields['data_parameter_type']['default'])->toBeIn(['form_data', 'json', 'query_string']);
});

it('defines the dashboard and pos default page entries as 25', function () {
    expect(config('settings.groups.dashboard.fields.default_datatable_page_entries.default'))->toBe(25);
    expect(config('settings.groups.pos.fields.default_datatable_page_entries.default'))->toBe(25);
});

it('defines the system settings incl 2-step auth and report settings', function () {
    $fields = config('settings.groups.system.fields');

    expect(array_keys($fields))->toContain('theme_color')
        ->toContain('default_datatable_page_entries')
        ->toContain('show_help_text')
        ->toContain('enable_2fa')
        ->toContain('timezone')
        ->toContain('date_format');
});
