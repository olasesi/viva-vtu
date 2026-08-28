<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('can get wallet balance', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000.00,
        'currency' => 'NGN',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/wallet/balance');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'balance' => 5000.00,
                'currency' => 'NGN',
            ],
        ]);
});

it('can create wallet with zero balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/wallet/balance');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'balance' => 0.00,
            ],
        ]);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 0,
    ]);
});

it('can get transaction history', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000.00,
        'currency' => 'NGN',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => 'FUND-TEST-001',
        'description' => 'Wallet funding',
        'amount' => 5000.00,
        'status' => 'successful',
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/wallet/history');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonCount(1, 'data.data');
});

it('returns empty transaction history for new user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/wallet/history');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonCount(0, 'data.data');
});

it('rejects unauthenticated wallet balance request', function () {
    $response = $this->getJson('/api/wallet/balance');

    $response->assertStatus(401);
});

it('can get transaction detail', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000.00,
        'currency' => 'NGN',
    ]);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => 'FUND-TEST-002',
        'description' => 'Wallet funding',
        'amount' => 5000.00,
        'status' => 'successful',
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson("/api/transactions/{$transaction->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'reference' => 'FUND-TEST-002',
                'amount' => 5000.00,
            ],
        ]);
});

it('returns 404 for non-existent transaction', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/transactions/99999');

    $response->assertStatus(404);
});

it('can filter transactions by category', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000.00,
        'currency' => 'NGN',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'category' => 'airtime',
        'reference' => 'AIR-TEST-001',
        'description' => 'Airtime purchase',
        'amount' => 500.00,
        'status' => 'successful',
        'completed_at' => now(),
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => 'FUND-TEST-003',
        'description' => 'Wallet funding',
        'amount' => 5000.00,
        'status' => 'successful',
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/transactions?category=airtime');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonCount(1, 'data.data');
});

it('can filter transactions by status', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000.00,
        'currency' => 'NGN',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'category' => 'airtime',
        'reference' => 'AIR-TEST-002',
        'description' => 'Airtime purchase',
        'amount' => 500.00,
        'status' => 'failed',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => 'FUND-TEST-004',
        'description' => 'Wallet funding',
        'amount' => 5000.00,
        'status' => 'successful',
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/transactions?status=failed');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonCount(1, 'data.data');
});

it('can create wallet fund payment initialization', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/wallet/fund', [
            'amount' => 1000,
            'email' => 'test@example.com',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'authorization_url',
                'access_code',
                'reference',
            ],
        ]);
});

it('validates minimum fund amount', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/wallet/fund', [
            'amount' => 50,
            'email' => $user->email,
        ]);

    $response->assertStatus(422);
});

it('validates airtime purchase minimum amount', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 10000.00,
        'currency' => 'NGN',
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/airtime', [
            'phone_number' => '08012345678',
            'amount' => 10,
            'network' => 'mtn',
        ]);

    $response->assertStatus(422);
});

it('validates required fields for airtime purchase', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/airtime', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number', 'amount', 'network']);
});
