<?php

use App\Models\User;
use App\Models\Wallet;
use App\Services\SettingService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
    FakeProvider::$verifyCalls = [];
});

function routeCategoryThroughFakeProvider(string $mode): void
{
    config([
        'aggregators.providers.fake' => [
            'class' => FakeProvider::class,
            'label' => 'Fake',
            'enabled' => true,
            'fake_mode' => $mode,
        ],
        'aggregators.routing' => array_fill_keys(['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'], ['fake']),
    ]);
}

function enablePreValidation(): void
{
    app(SettingService::class)->set('api', 'pre_validation_enabled', true);
}

function makeMeterPurchase(int $userId, array $params = []): array
{
    return app(TransactionService::class)->execute('electricity', $userId, array_merge([
        'disco' => 'abuja-electric',
        'meter_number' => '12345678901',
        'meter_type' => 'prepaid',
        'amount' => 2000,
    ], $params));
}

function makeSmartcardPurchase(int $userId, array $params = []): array
{
    return app(TransactionService::class)->execute('cable', $userId, array_merge([
        'cable' => 'dstv',
        'smartcard_number' => '7034567890',
        'package' => 'Compact Plus',
        'amount' => 25000,
    ], $params));
}

it('skips verification when pre-validation is disabled', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeCategoryThroughFakeProvider('success');

    $result = makeMeterPurchase($user->id);

    expect($result['status'])->toBe('successful')
        ->and(FakeProvider::$verifyCalls)->toBeEmpty()
        ->and(FakeProvider::$calls)->toHaveCount(1);
});

it('verifies the meter before debiting when pre-validation is enabled', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeCategoryThroughFakeProvider('success');
    enablePreValidation();

    $result = makeMeterPurchase($user->id);

    expect($result['status'])->toBe('successful')
        ->and(FakeProvider::$verifyCalls)->toBe([
            ['serviceID' => 'abuja-electric', 'billersCode' => '12345678901'],
        ])
        ->and(FakeProvider::$calls)->toHaveCount(1);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'electricity',
        'status' => 'successful',
    ]);
});

it('blocks the purchase on a definitive meter rejection without debiting', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeCategoryThroughFakeProvider('verify_rejected');
    enablePreValidation();

    $result = makeMeterPurchase($user->id);

    expect($result['status'])->toBe('validation_failed')
        ->and($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Invalid meter')
        ->and(FakeProvider::$verifyCalls)->toHaveCount(1)
        ->and(FakeProvider::$calls)->toBeEmpty();

    $this->assertDatabaseMissing('transactions', ['user_id' => $user->id])
        ->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 5000,
        ]);
});

it('proceeds best-effort when the verifier is unreachable', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeCategoryThroughFakeProvider('verify_unavailable');
    enablePreValidation();

    $result = makeMeterPurchase($user->id);

    expect($result['status'])->toBe('successful')
        ->and(FakeProvider::$verifyCalls)->toHaveCount(1)
        ->and(FakeProvider::$calls)->toHaveCount(1);
});

it('verifies the smartcard for cable purchases', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 30000, 'currency' => 'NGN']);
    routeCategoryThroughFakeProvider('success');
    enablePreValidation();

    $result = makeSmartcardPurchase($user->id);

    expect($result['status'])->toBe('successful')
        ->and(FakeProvider::$verifyCalls)->toBe([
            ['serviceID' => 'dstv', 'billersCode' => '7034567890'],
        ]);
});

it('never pre-validates phone-based categories', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeCategoryThroughFakeProvider('success');
    enablePreValidation();

    $result = app(TransactionService::class)->execute('data', $user->id, [
        'phone_number' => '08031234567',
        'amount' => 1000,
        'network' => 'mtn',
        'plan' => 'gift-1gb',
    ]);

    expect($result['status'])->toBe('successful')
        ->and(FakeProvider::$verifyCalls)->toBeEmpty()
        ->and(FakeProvider::$calls)->toHaveCount(1);
});
