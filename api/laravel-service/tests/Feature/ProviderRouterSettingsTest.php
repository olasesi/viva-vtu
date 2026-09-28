<?php

use App\Services\ProviderRouter;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function providerRouter(): ProviderRouter
{
    return app(ProviderRouter::class);
}

function providerConfig(mixed $provider): array
{
    $property = new ReflectionProperty($provider, 'config');
    $property->setAccessible(true);

    return $property->getValue($provider);
}

it('keeps the config routing order when the provider mode is auto', function () {
    config([
        'aggregators.providers.aidapay.enabled' => true,
        'aggregators.providers.easyaccess.enabled' => true,
    ]);

    $providers = providerRouter()->providersFor('data');

    expect(array_keys($providers))->toBe(['vtpass', 'aidapay', 'easyaccess']);
});

it('prefers aidapay when the runtime provider mode is aida', function () {
    app(SettingService::class)->set('api', 'provider_mode', 'aida');

    config([
        'aggregators.providers.aidapay.enabled' => true,
        'aggregators.providers.easyaccess.enabled' => true,
    ]);

    $providers = providerRouter()->providersFor('data');

    expect(array_keys($providers))->toBe(['aidapay', 'vtpass', 'easyaccess']);
});

it('prefers easyaccess when the runtime provider mode is easy_access', function () {
    app(SettingService::class)->set('api', 'provider_mode', 'easy_access');

    config([
        'aggregators.providers.aidapay.enabled' => true,
        'aggregators.providers.easyaccess.enabled' => true,
    ]);

    $providers = providerRouter()->providersFor('data');

    expect(array_keys($providers))->toBe(['easyaccess', 'vtpass', 'aidapay']);
});

it('overlays runtime credentials onto the aidapay config', function () {
    app(SettingService::class)->set('api', 'aida_base_url', 'https://aidapay.example.test');
    app(SettingService::class)->set('api', 'aida_secret_key', 'runtime-token');
    app(SettingService::class)->set('api', 'aida_account_pin', '1234');

    config(['aggregators.providers.aidapay.enabled' => true]);

    $providers = providerRouter()->providersFor('data');
    $config = providerConfig($providers['aidapay']);

    expect($config['base_url'])->toBe('https://aidapay.example.test');
    expect($config['api_token'])->toBe('runtime-token');
    expect($config['account_pin'])->toBe('1234');
});

it('overlays runtime credentials onto the easyaccess config', function () {
    app(SettingService::class)->set('api', 'easy_access_base_url', 'https://easyaccess.example.test');
    app(SettingService::class)->set('api', 'easy_access_token', 'runtime-ea-token');

    config(['aggregators.providers.easyaccess.enabled' => true]);

    $providers = providerRouter()->providersFor('data');
    $config = providerConfig($providers['easyaccess']);

    expect($config['base_url'])->toBe('https://easyaccess.example.test');
    expect($config['api_token'])->toBe('runtime-ea-token');
});

it('keeps env credentials when runtime overrides are not set', function () {
    config([
        'aggregators.providers.aidapay.enabled' => true,
        'aggregators.providers.aidapay.base_url' => 'https://env.example.test',
        'aggregators.providers.aidapay.api_token' => 'env-token',
    ]);

    $providers = providerRouter()->providersFor('data');
    $config = providerConfig($providers['aidapay']);

    expect($config['base_url'])->toBe('https://env.example.test');
    expect($config['api_token'])->toBe('env-token');
});

it('disables easyaccess when the runtime setting explicitly disables it', function () {
    app(SettingService::class)->set('api', 'easy_access_enabled', false);

    config([
        'aggregators.providers.aidapay.enabled' => true,
        'aggregators.providers.easyaccess.enabled' => true,
    ]);

    $providers = providerRouter()->providersFor('data');

    expect(array_keys($providers))->toBe(['vtpass', 'aidapay'])
        ->not->toContain('easyaccess');
});

it('enables easyaccess when the runtime setting explicitly enables it', function () {
    app(SettingService::class)->set('api', 'easy_access_enabled', true);

    config([
        'aggregators.providers.aidapay.enabled' => true,
        'aggregators.providers.easyaccess.enabled' => false,
    ]);

    $providers = providerRouter()->providersFor('data');

    expect(array_keys($providers))->toContain('easyaccess');
});
