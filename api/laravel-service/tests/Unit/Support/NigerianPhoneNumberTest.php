<?php

use App\Support\Phones\NigerianPhoneNumber;

it('normalizes local, international and spaced phone numbers', function () {
    expect(NigerianPhoneNumber::normalize('08031234567'))->toBe('08031234567')
        ->and(NigerianPhoneNumber::normalize('+2348031234567'))->toBe('08031234567')
        ->and(NigerianPhoneNumber::normalize('2348031234567'))->toBe('08031234567')
        ->and(NigerianPhoneNumber::normalize('8031234567'))->toBe('08031234567')
        ->and(NigerianPhoneNumber::normalize('0803 123 4567'))->toBe('08031234567')
        ->and(NigerianPhoneNumber::normalize('0803-123-4567'))->toBe('08031234567');
});

it('returns null when the phone cannot be normalized', function () {
    expect(NigerianPhoneNumber::normalize(''))->toBeNull()
        ->and(NigerianPhoneNumber::normalize('12345'))->toBeNull()
        ->and(NigerianPhoneNumber::normalize('hello'))->toBeNull()
        ->and(NigerianPhoneNumber::normalize('0803123456'))->toBeNull()
        ->and(NigerianPhoneNumber::normalize('08031234567890'))->toBeNull();
});

it('recognises valid Nigerian mobile numbers', function () {
    expect(NigerianPhoneNumber::isValid('08031234567'))->toBeTrue()
        ->and(NigerianPhoneNumber::isValid('+2348087654321'))->toBeTrue()
        ->and(NigerianPhoneNumber::isValid('09051234567'))->toBeTrue()
        ->and(NigerianPhoneNumber::isValid('08131234567'))->toBeTrue()
        ->and(NigerianPhoneNumber::isValid('080123456'))->toBeFalse()
        ->and(NigerianPhoneNumber::isValid('0112345678'))->toBeFalse();
});

it('detects the correct network from the prefix', function () {
    expect(NigerianPhoneNumber::networkFor('08031234567'))->toBe('mtn')
        ->and(NigerianPhoneNumber::networkFor('08139345678'))->toBe('mtn')
        ->and(NigerianPhoneNumber::networkFor('08051234567'))->toBe('glo')
        ->and(NigerianPhoneNumber::networkFor('09081234567'))->toBe('9mobile')
        ->and(NigerianPhoneNumber::networkFor('08021234567'))->toBe('airtel')
        ->and(NigerianPhoneNumber::networkFor('07001234567'))->toBeNull();
});

it('passes a phone number whose prefix matches the selected network', function () {
    $check = NigerianPhoneNumber::validateForNetwork('08031234567', 'mtn');

    expect($check['valid'])->toBeTrue()
        ->and($check['network'])->toBe('mtn')
        ->and($check['network_match'])->toBeTrue();
});

it('rejects a phone number whose prefix contradicts the selected network', function () {
    $check = NigerianPhoneNumber::validateForNetwork('08051234567', 'mtn');

    expect($check['valid'])->toBeFalse()
        ->and($check['network'])->toBe('glo')
        ->and($check['network_match'])->toBeFalse()
        ->and($check['reason'])->toContain('Glo')
        ->and($check['reason'])->toContain('MTN');
});

it('does not reject a phone with an unknown prefix', function () {
    $check = NigerianPhoneNumber::validateForNetwork('07001234567', 'mtn');

    expect($check['valid'])->toBeTrue()
        ->and($check['network'])->toBeNull()
        ->and($check['network_match'])->toBeNull();
});

it('rejects an invalid phone number', function () {
    $check = NigerianPhoneNumber::validateForNetwork('12345', 'mtn');

    expect($check['valid'])->toBeFalse()
        ->and($check['reason'])->toBe('Invalid phone number. Expected a valid Nigerian mobile number.');
});

it('tolerates a missing declared network', function () {
    $check = NigerianPhoneNumber::validateForNetwork('08031234567', '');

    expect($check['valid'])->toBeTrue()
        ->and($check['declared_network'])->toBeNull()
        ->and($check['network_match'])->toBeNull();
});

it('ignores unknown declared networks', function () {
    $check = NigerianPhoneNumber::validateForNetwork('08031234567', 'spacetel');

    expect($check['valid'])->toBeTrue()
        ->and($check['declared_network'])->toBeNull();
});
