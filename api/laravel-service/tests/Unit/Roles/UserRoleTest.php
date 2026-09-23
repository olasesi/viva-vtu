<?php

use App\Support\Roles\UserRole;

it('exposes every supported user type', function () {
    expect(UserRole::all())->toBe([
        'user',
        'admin',
        'agent',
        'merchant',
        'reseller',
        'distributor',
        'sub_reseller',
    ]);
});

it('defaults new accounts to the regular user role', function () {
    expect(UserRole::default())->toBe('user');
});

it('marks admin as the only administrative role', function () {
    expect(UserRole::isAdmin('admin'))->toBeTrue();

    foreach (['user', 'agent', 'merchant', 'reseller', 'distributor', 'sub_reseller'] as $role) {
        expect(UserRole::isAdmin($role))->toBeFalse();
    }
});

it('normalizes role casing to lowercase', function () {
    expect(UserRole::normalize('ADMIN'))->toBe('admin');
    expect(UserRole::normalize('Merchant'))->toBe('merchant');
    expect(UserRole::normalize('sub_reseller'))->toBe('sub_reseller');
});

it('falls back to the default role for unknown or missing roles', function () {
    expect(UserRole::normalize(null))->toBe('user');
    expect(UserRole::normalize('super_hero'))->toBe('user');
    expect(UserRole::normalize(''))->toBe('user');
});
