<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to transfer', function () {
    $this->postJson('/api/wallet/transfer', [
        'recipient' => 'friend@example.com',
        'amount' => 500,
    ])->assertStatus(401);
});

it('validates the transfer payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/wallet/transfer', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipient', 'amount']);
});

it('transfers balance to a recipient identified by email', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);
    Wallet::create(['user_id' => $recipient->id, 'balance' => 1000, 'currency' => 'NGN']);

    $response = $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $recipient->email,
            'amount' => 1500,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.amount', 1500)
        ->assertJsonPath('data.balance', 3500)
        ->assertJsonPath('data.currency', 'NGN');

    $this->assertDatabaseHas('wallets', ['user_id' => $sender->id, 'balance' => 3500]);
    $this->assertDatabaseHas('wallets', ['user_id' => $recipient->id, 'balance' => 2500]);

    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseHas('transactions', [
        'user_id' => $sender->id,
        'type' => 'debit',
        'category' => 'transfer',
        'amount' => 1500,
    ]);
    $this->assertDatabaseHas('transactions', [
        'user_id' => $recipient->id,
        'type' => 'credit',
        'category' => 'transfer',
        'amount' => 1500,
    ]);
});

it('resolves a recipient by phone number', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['phone' => '08031234567']);

    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);

    $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => '08031234567',
            'amount' => 800,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('wallets', ['user_id' => $sender->id, 'balance' => 4200]);
    $this->assertDatabaseHas('wallets', ['user_id' => $recipient->id, 'balance' => 800]);
});

it('creates a wallet for a recipient that has none', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);

    $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $recipient->email,
            'amount' => 2000,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('wallets', ['user_id' => $recipient->id, 'balance' => 2000]);
});

it('rejects a transfer with insufficient balance', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Wallet::create(['user_id' => $sender->id, 'balance' => 500, 'currency' => 'NGN']);

    $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $recipient->email,
            'amount' => 5000,
        ])
        ->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Insufficient wallet balance.',
        ]);

    $this->assertDatabaseHas('wallets', ['user_id' => $sender->id, 'balance' => 500]);
    $this->assertDatabaseCount('transactions', 0);
});

it('rejects a self-transfer', function () {
    $user = User::factory()->create();

    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    $this->actingAs($user, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $user->email,
            'amount' => 500,
        ])
        ->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'You cannot transfer money to yourself.',
        ]);

    $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 5000]);
});

it('rejects a transfer to an unknown recipient', function () {
    $user = User::factory()->create();

    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    $this->actingAs($user, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => 'nobody@example.com',
            'amount' => 500,
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});
