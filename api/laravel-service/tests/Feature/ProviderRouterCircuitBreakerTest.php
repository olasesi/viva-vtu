<?php

use App\Models\User;
use App\Models\Wallet;
use App\Services\ProviderRouter;
use App\Services\SettingService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    FakeProvider::$calls = [];
    FakeProvider::$verifyCalls = [];

    config([
        'aggregators.health' => [
            'failure_threshold' => 3,
            'cooldown_minutes' => 5,
        ],
        'aggregators.routing.data' => ['vtpass', 'aidapay'],
        'aggregators.providers.vtpass.enabled' => true,
        'aggregators.providers.aidapay.enabled' => true,
    ]);

    app(SettingService::class)->set('api', 'provider_mode', 'auto');
});

function breaker(): ProviderRouter
{
    return app(ProviderRouter::class);
}

function dataPool(): array
{
    return array_keys(breaker()->providersFor('data'));
}

it('keeps both providers in the pool before any failures', function () {
    expect(dataPool())->toBe(['vtpass', 'aidapay'])
        ->and(Cache::get('aggregator_failures:vtpass'))->toBeNull()
        ->and(Cache::get('aggregator_failures:aidapay'))->toBeNull();
});

it('keeps a provider in the pool below the failure threshold', function () {
    breaker()->markFailure('vtpass');
    breaker()->markFailure('vtpass');

    expect(dataPool())->toBe(['vtpass', 'aidapay'])
        ->and(Cache::get('aggregator_failures:vtpass'))->toBe(2)
        ->and(Cache::get('aggregator_tripped_at:vtpass'))->toBeNull();
});

it('trips the breaker at the configured consecutive failure count', function () {
    breaker()->markFailure('vtpass');
    breaker()->markFailure('vtpass');
    expect(dataPool())->toBe(['vtpass', 'aidapay']);

    breaker()->markFailure('vtpass');

    expect(dataPool())->toBe(['aidapay'])
        ->and(Cache::get('aggregator_failures:vtpass'))->toBe(3)
        ->and(Cache::get('aggregator_tripped_at:vtpass'))->not->toBeNull();
});

it('removes a tripped provider from the routing pool', function () {
    foreach (range(1, 3) as $ignored) {
        breaker()->markFailure('vtpass');
    }

    expect(dataPool())->toBe(['aidapay']);
});

it('resets the failure count when a provider succeeds mid-streak', function () {
    breaker()->markFailure('vtpass');
    breaker()->markFailure('vtpass');
    breaker()->markSuccess('vtpass');

    expect(Cache::get('aggregator_failures:vtpass'))->toBeNull()
        ->and(Cache::get('aggregator_tripped_at:vtpass'))->toBeNull()
        ->and(dataPool())->toBe(['vtpass', 'aidapay']);
});

it('reopens a tripped breaker on success', function () {
    foreach (range(1, 3) as $ignored) {
        breaker()->markFailure('vtpass');
    }

    expect(dataPool())->toBe(['aidapay']);

    breaker()->markSuccess('vtpass');

    expect(dataPool())->toBe(['vtpass', 'aidapay'])
        ->and(Cache::get('aggregator_tripped_at:vtpass'))->toBeNull();
});

it('tracks failures per provider independently', function () {
    foreach (range(1, 3) as $ignored) {
        breaker()->markFailure('aidapay');
    }

    expect(dataPool())->toBe(['vtpass'])
        ->and(Cache::get('aggregator_failures:vtpass'))->toBeNull();
});

it('keeps a tripped provider excluded during the cooldown window', function () {
    foreach (range(1, 3) as $ignored) {
        breaker()->markFailure('vtpass');
    }

    $this->travel(4)->minutes();

    expect(dataPool())->toBe(['aidapay']);
});

it('recovers a provider into the pool once the cooldown elapses', function () {
    foreach (range(1, 3) as $ignored) {
        breaker()->markFailure('vtpass');
    }

    $this->travel(4)->minutes();
    $this->travel(2)->minutes();

    expect(dataPool())->toBe(['vtpass', 'aidapay'])
        ->and(Cache::get('aggregator_failures:vtpass'))->toBeNull()
        ->and(Cache::get('aggregator_tripped_at:vtpass'))->toBeNull();
});

it('trips the breaker under repeated purchase failures while refunding every attempt', function () {
    config([
        'aggregators.providers.fake' => [
            'class' => FakeProvider::class,
            'label' => 'Fake',
            'enabled' => true,
            'fake_mode' => 'definitive',
        ],
        'aggregators.routing.data' => ['fake'],
    ]);

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    foreach (range(1, 4) as $attempt) {
        $result = app(TransactionService::class)->execute('data', $user->id, [
            'phone_number' => '08031234567',
            'amount' => 1000,
            'network' => 'mtn',
            'plan' => 'gift-1gb',
        ]);

        expect($result['status'])->toBe('failed');
    }

    expect(dataPool())->toBe([])
        ->and(Cache::get('aggregator_failures:fake'))->toBe(3)
        ->and(FakeProvider::$calls)->toHaveCount(3);

    $this->assertDatabaseCount('transactions', 8);
    $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'category' => 'data', 'status' => 'failed']);
    expect(DB::table('transactions')->where('user_id', $user->id)->where('status', 'failed')->count())->toBe(4)
        ->and(DB::table('transactions')->where('user_id', $user->id)->where('status', 'successful')->count())->toBe(4);
    $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 5000]);
});
