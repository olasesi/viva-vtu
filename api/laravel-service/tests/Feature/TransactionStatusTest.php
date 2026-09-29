<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
    FakeProvider::$verifyCalls = [];
});

function routeStatusThroughFakeProvider(string $mode): void
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

function statusTransaction($user, $wallet, array $overrides = []): Transaction
{
    return Transaction::create(array_merge([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'category' => 'data',
        'reference' => 'DAT-POLL-001',
        'description' => 'Data purchase',
        'amount' => 1000,
        'status' => 'pending',
        'provider' => 'fake',
        'attempts' => 1,
    ], $overrides));
}

function pollStatus(string $reference, array $query = []): TestResponse
{
    return test()->getJson('/api/transactions/status/'.$reference.'?'.http_build_query($query));
}

it('requires authentication to poll a transaction status', function () {
    $this->getJson('/api/transactions/status/DAT-POLL-001')->assertStatus(401);
});

it('returns 404 for an unknown reference', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-999')
        ->assertStatus(404);
});

it('returns 404 for another users transaction', function () {
    $owner = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $owner->id, 'balance' => 5000, 'currency' => 'NGN']);
    statusTransaction($owner, $wallet);

    $other = User::factory()->create();

    $this->actingAs($other, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-001')
        ->assertStatus(404);
});

it('reports the status of a successful transaction by reference', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    $transaction = statusTransaction($user, $wallet, [
        'status' => 'successful',
        'provider_reference' => 'TXN-FAKE-001',
        'completed_at' => now(),
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-001')
        ->assertOk()
        ->assertJsonPath('data.reference', $transaction->reference)
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.provider', 'fake')
        ->assertJsonPath('data.provider_reference', 'TXN-FAKE-001')
        ->assertJsonPath('data.amount', '1000.00');
});

it('reports a pending transaction without advancing it unless requery is requested', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeStatusThroughFakeProvider('ambiguous');
    statusTransaction($user, $wallet);

    $this->actingAs($user, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-001')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.attempts', 1);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'DAT-POLL-001',
        'status' => 'pending',
        'attempts' => 1,
    ]);
});

it('resolves a pending transaction to successful when requery is requested', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeStatusThroughFakeProvider('success');
    statusTransaction($user, $wallet);

    $this->actingAs($user, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-001?requery=1')
        ->assertOk()
        ->assertJsonPath('data.status', 'successful');
});

it('reverses and refunds a pending transaction when requery confirms failure', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeStatusThroughFakeProvider('definitive');
    statusTransaction($user, $wallet);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-001?requery=1')
        ->assertOk()
        ->assertJsonPath('data.status', 'failed');

    expect($response->json('data.reversed_at'))->not->toBeNull();

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 6000,
    ]);
});

it('never requeries a successful transaction', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeStatusThroughFakeProvider('definitive');
    $transaction = statusTransaction($user, $wallet, ['status' => 'successful', 'completed_at' => now()]);

    $this->actingAs($user, 'api')
        ->getJson('/api/transactions/status/DAT-POLL-001?requery=1')
        ->assertOk()
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.attempts', 1);

    $this->assertDatabaseHas('transactions', [
        'reference' => $transaction->reference,
        'status' => 'successful',
        'attempts' => 1,
    ]);
});
