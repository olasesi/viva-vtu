<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function configureAidaPayWebhook(): void
{
    config([
        'aggregators.providers.aidapay' => [
            'label' => 'AidaPay',
            'enabled' => true,
            'api_token' => 'test-secret-token',
            'account_pin' => '1234',
            'webhook' => [
                'signature_header' => 'Signature',
                'secret_from' => 'api_token',
                'successful_statuses' => ['Completed'],
                'processing_statuses' => ['Processing', 'Pending'],
                'failed_statuses' => ['Refund', 'Cancelled'],
                'status_path' => 'status',
                'reference_path' => 'ref',
                'hash_path' => 'transaction_hash',
            ],
        ],
    ]);
}

function pendingTransaction($user, $wallet, array $overrides = []): Transaction
{
    return Transaction::create(array_merge([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'category' => 'data',
        'reference' => 'AIR-WEBHOOK-001',
        'description' => 'Data purchase',
        'amount' => 1000,
        'status' => 'pending',
        'provider' => 'aidapay',
        'attempts' => 2,
    ], $overrides));
}

function postAidaPayWebhook(array $payload, array $headers = []): TestResponse
{
    $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $signature = hash_hmac('sha256', $rawJson, 'test-secret-token');

    return test()->postJson('/api/webhook/aggregator/aidapay', $payload, array_merge([
        'Signature' => $signature,
    ], $headers));
}

it('reconciles a pending transaction as successful on a Completed webhook', function () {
    configureAidaPayWebhook();

    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4000, 'currency' => 'NGN']);
    pendingTransaction($user, $wallet);

    $response = postAidaPayWebhook([
        'type' => 'transaction',
        'transaction_hash' => 'TXabc123def456',
        'ref' => 'AIR-WEBHOOK-001',
        'status' => 'Completed',
        'amount' => 1000,
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'AIR-WEBHOOK-001',
        'status' => 'successful',
        'provider_reference' => 'TXabc123def456',
    ]);
});

it('reverses and refunds the wallet on a Refund webhook', function () {
    configureAidaPayWebhook();

    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4000, 'currency' => 'NGN']);
    pendingTransaction($user, $wallet, ['reference' => 'AIR-WEBHOOK-002']);

    $response = postAidaPayWebhook([
        'type' => 'transaction',
        'transaction_hash' => 'TXrefunded123',
        'ref' => 'AIR-WEBHOOK-002',
        'status' => 'Refund',
        'amount' => 1000,
    ]);

    $response->assertOk();

    $transaction = Transaction::where('reference', 'AIR-WEBHOOK-002')->first();

    expect($transaction->status)->toBe('failed')
        ->and($transaction->reversed_at)->not->toBeNull();

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 5000,
    ]);
});

it('matches a pending transaction by provider_reference when no ref is sent', function () {
    configureAidaPayWebhook();

    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4000, 'currency' => 'NGN']);
    pendingTransaction($user, $wallet, ['reference' => 'AIR-WEBHOOK-003', 'provider_reference' => 'TXbyhash123']);

    $response = postAidaPayWebhook([
        'type' => 'transaction',
        'transaction_hash' => 'TXbyhash123',
        'status' => 'Completed',
        'amount' => 1000,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('transactions', [
        'reference' => 'AIR-WEBHOOK-003',
        'status' => 'successful',
    ]);
});

it('leaves the transaction pending when the webhook cannot be matched', function () {
    configureAidaPayWebhook();

    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4000, 'currency' => 'NGN']);
    pendingTransaction($user, $wallet, ['reference' => 'AIR-WEBHOOK-004']);

    $response = postAidaPayWebhook([
        'type' => 'transaction',
        'transaction_hash' => 'TXunknownhash',
        'status' => 'Completed',
        'amount' => 1000,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('transactions', [
        'reference' => 'AIR-WEBHOOK-004',
        'status' => 'pending',
    ]);
});

it('rejects webhooks with an invalid signature', function () {
    configureAidaPayWebhook();

    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4000, 'currency' => 'NGN']);
    pendingTransaction($user, $wallet);

    $rawJson = json_encode([
        'type' => 'transaction',
        'transaction_hash' => 'TXevil',
        'ref' => 'AIR-WEBHOOK-001',
        'status' => 'Completed',
        'amount' => 1000,
    ], JSON_UNESCAPED_SLASHES);

    $response = test()->postJson(
        '/api/webhook/aggregator/aidapay',
        json_decode($rawJson, true),
        ['Signature' => str_repeat('0', 64)]
    );

    $response->assertStatus(403)->assertJson(['message' => 'Invalid signature']);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'AIR-WEBHOOK-001',
        'status' => 'pending',
    ]);
});

it('returns 404 for an unknown aggregator webhook', function () {
    $response = test()->postJson('/api/webhook/aggregator/not-a-provider', []);

    $response->assertStatus(404);
});
