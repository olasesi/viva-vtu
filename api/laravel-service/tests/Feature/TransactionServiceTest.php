<?php

use App\Jobs\RequeryPendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
});

function routeDataPurchasesThroughFakeProvider(string $mode): void
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

function makeDataPurchase(int $userId, array $params = []): array
{
    return app(TransactionService::class)->execute('data', $userId, array_merge([
        'phone_number' => '08012345678',
        'amount' => 1000,
        'network' => 'mtn',
        'plan' => 'gift-1gb',
    ], $params));
}

function makeExamPurchase(int $userId, array $params = []): array
{
    return app(TransactionService::class)->execute('education', $userId, array_merge([
        'exam_type' => 'waec',
        'amount' => 1500,
        'quantity' => 1,
    ], $params));
}

function makeStreamingPurchase(int $userId, array $params = []): array
{
    return app(TransactionService::class)->execute('streaming', $userId, array_merge([
        'platform' => 'netflix',
        'plan' => '1-month',
        'amount' => 2900,
        'recipient' => '08012345678',
    ], $params));
}

it('debits wallet and marks data purchase successful on provider success', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('success');

    $result = makeDataPurchase($user->id);

    expect($result['status'])->toBe('successful');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'data',
        'type' => 'debit',
        'status' => 'successful',
        'provider' => 'fake',
        'provider_reference' => 'TXN-FAKE-001',
    ]);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 4000,
    ]);
});

it('passes request_id and delivery params to the provider', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('success');

    $result = makeDataPurchase($user->id);

    $sent = FakeProvider::$calls[0];

    expect($sent['request_id'])->toBe($result['transaction']->reference)
        ->and($sent['phone_number'])->toBe('08012345678')
        ->and($sent['plan'])->toBe('gift-1gb');
});

it('auto-reverses the wallet when the provider definitively rejects', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('definitive');

    $result = makeDataPurchase($user->id);

    expect($result['status'])->toBe('failed');

    $transaction = Transaction::where('user_id', $user->id)->where('category', 'data')->first();

    expect($transaction->status)->toBe('failed')
        ->and($transaction->last_error)->toBe('All providers failed for data')
        ->and($transaction->reversed_at)->not->toBeNull();

    $metadata = $transaction->metadata ?? [];

    expect($metadata['reversal_reference'])->toBe('REV-'.$transaction->reference);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 5000,
    ]);
});

it('keeps a transaction pending and schedules requery on ambiguous provider response', function () {
    Queue::fake();

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('ambiguous');

    $result = makeDataPurchase($user->id);

    expect($result['status'])->toBe('processing');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'data',
        'status' => 'pending',
        'provider' => 'fake',
    ]);

    Queue::assertPushed(RequeryPendingTransaction::class, function (RequeryPendingTransaction $job) {
        return $job->transactionId > 0;
    });
});

it('purchases exam pins for the education vertical', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('success');

    $result = makeExamPurchase($user->id);

    expect($result['status'])->toBe('successful')
        ->and($result['data']['exam_type'])->toBe('waec');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'education',
        'type' => 'debit',
        'status' => 'successful',
        'provider' => 'fake',
    ]);

    $sent = FakeProvider::$calls[0];

    expect($sent['exam_type'])->toBe('waec')
        ->and($sent['request_id'])->toBe($result['transaction']->reference);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 3500,
    ]);
});

it('purchases streaming subscriptions for the streaming vertical', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('success');

    $result = makeStreamingPurchase($user->id);

    expect($result['status'])->toBe('successful');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'streaming',
        'status' => 'successful',
        'provider' => 'fake',
    ]);

    $sent = FakeProvider::$calls[0];

    expect($sent['platform'])->toBe('netflix')
        ->and($sent['plan'])->toBe('1-month');

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 2100,
    ]);
});

it('keeps an education purchase pending and requeries on ambiguous response', function () {
    Queue::fake();

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('ambiguous');

    $result = makeExamPurchase($user->id);

    expect($result['status'])->toBe('processing');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'education',
        'status' => 'pending',
    ]);

    Queue::assertPushed(RequeryPendingTransaction::class);
});

it('rejects a purchase when the wallet balance is insufficient', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 100, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('success');

    $result = makeDataPurchase($user->id);

    expect($result['status'])->toBe('failed')
        ->and($result['message'])->toBe('Insufficient wallet balance');

    $this->assertDatabaseCount('transactions', 0);
});

it('reverses a confirmed-failed requery', function () {
    $user = User::factory()->create();
    // Wallet already debited for the pending purchase (5000 - 1000).
    Wallet::create(['user_id' => $user->id, 'balance' => 4000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('definitive');

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::where('user_id', $user->id)->first()->id,
        'type' => 'debit',
        'category' => 'data',
        'reference' => 'DAT-REQUERY-001',
        'description' => 'Data purchase',
        'amount' => 1000,
        'status' => 'pending',
        'provider' => 'fake',
        'attempts' => 2,
    ]);

    // Fake provider is in "definitive" mode, so a requery confirms failure.
    app(TransactionService::class)->requery($transaction->fresh());

    expect($transaction->fresh()->status)->toBe('failed')
        ->and($transaction->fresh()->reversed_at)->not->toBeNull();

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 5000,
    ]);
});

it('marks a pending transaction successful when a requery succeeds', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeDataPurchasesThroughFakeProvider('success');

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::where('user_id', $user->id)->first()->id,
        'type' => 'debit',
        'category' => 'data',
        'reference' => 'DAT-REQUERY-002',
        'description' => 'Data purchase',
        'amount' => 1000,
        'status' => 'pending',
        'provider' => 'fake',
        'attempts' => 2,
    ]);

    app(TransactionService::class)->requery($transaction->fresh());

    expect($transaction->fresh()->status)->toBe('successful');
});
