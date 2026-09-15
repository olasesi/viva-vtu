<?php

use App\Support\Settings\SettingCaster;

it('casts a string value to and from the database', function () {
    expect(SettingCaster::toDb('string', 'Viva VTU'))->toBe('"Viva VTU"')
        ->and(SettingCaster::fromDb('string', '"Viva VTU"'))->toBe('Viva VTU');
});

it('casts a boolean value to and from the database', function () {
    expect(SettingCaster::toDb('boolean', true))->toBe('true')
        ->and(SettingCaster::fromDb('boolean', 'true'))->toBeTrue()
        ->and(SettingCaster::fromDb('boolean', 'false'))->toBeFalse();
});

it('casts an integer value to and from the database', function () {
    expect(SettingCaster::toDb('integer', '25'))->toBe('25')
        ->and(SettingCaster::fromDb('integer', '25'))->toBe(25)
        ->and(SettingCaster::toDb('integer', 25))->toBe('25');
});

it('casts a decimal value to and from the database', function () {
    expect(SettingCaster::toDb('decimal', '7.5'))->toBe('7.5')
        ->and(SettingCaster::fromDb('decimal', '7.5'))->toBe(7.5)
        ->and(SettingCaster::toDb('decimal', 7.5))->toBe('7.5');
});

it('casts an enum value to and from the database', function () {
    expect(SettingCaster::toDb('enum', 'inclusive'))->toBe('"inclusive"')
        ->and(SettingCaster::fromDb('enum', '"inclusive"'))->toBe('inclusive');
});

it('casts an array value to and from the database', function () {
    $value = ['key' => 'Authorization', 'value' => 'Bearer abc'];

    expect(SettingCaster::toDb('array', $value))->toBe(json_encode($value))
        ->and(SettingCaster::fromDb('array', json_encode($value)))->toBe($value);
});

it('masks sensitive values so secrets are not leaked', function () {
    expect(SettingCaster::mask('super-secret-password'))->toBe('••••••••')
        ->and(SettingCaster::mask(''))->toBe('');
});

it('returns null from empty string for nullable string values', function () {
    expect(SettingCaster::fromDb('string', ''))->toBeNull()
        ->and(SettingCaster::fromDb('string', null))->toBeNull();
});
