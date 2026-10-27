<?php

use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('reports when no transaction pin is set', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/transaction-pin')
        ->assertStatus(200)
        ->assertJsonPath('data.enabled', false);
});

it('sets a transaction pin and hides the hash', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/transaction-pin', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $user->refresh();

    expect($user->hasTransactionPin())->toBeTrue()
        ->and(Hash::check('1234', $user->transaction_pin_hash))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('transaction_pin_hash');
});

it('rejects a pin that is not four digits', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/transaction-pin', [
            'pin' => '12',
            'pin_confirmation' => '12',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);
});

it('prevents setting the pin twice', function () {
    $user = User::factory()->create();
    app(TransactionPinService::class)->set($user, '1234');

    $this->actingAs($user, 'api')
        ->postJson('/api/transaction-pin', [
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])
        ->assertStatus(409);
});

it('changes the pin with the current pin', function () {
    $user = User::factory()->create();
    app(TransactionPinService::class)->set($user, '1234');

    $this->actingAs($user, 'api')
        ->postJson('/api/transaction-pin/change', [
            'current_pin' => '1234',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])
        ->assertStatus(200);

    expect(app(TransactionPinService::class)->verify($user->fresh(), '5678')['status'])->toBe('valid');
});

it('rejects a pin change with a wrong current pin', function () {
    $user = User::factory()->create();
    app(TransactionPinService::class)->set($user, '1234');

    $this->actingAs($user, 'api')
        ->postJson('/api/transaction-pin/change', [
            'current_pin' => '0000',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])
        ->assertStatus(422);
});

it('requires the pin for transfers once configured', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);
    Wallet::create(['user_id' => $recipient->id, 'balance' => 0, 'currency' => 'NGN']);

    app(TransactionPinService::class)->set($sender, '1234');

    $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $recipient->email,
            'amount' => 1000,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'transaction_pin_required');

    $this->assertDatabaseCount('transactions', 0);
});

it('rejects transfers with an invalid pin', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);

    app(TransactionPinService::class)->set($sender, '1234');

    $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $recipient->email,
            'amount' => 1000,
            'transaction_pin' => '9999',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'transaction_pin_invalid');

    $this->assertDatabaseCount('transactions', 0);
});

it('completes a transfer with the correct pin', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);
    Wallet::create(['user_id' => $recipient->id, 'balance' => 0, 'currency' => 'NGN']);

    app(TransactionPinService::class)->set($sender, '1234');

    $this->actingAs($sender, 'api')
        ->postJson('/api/wallet/transfer', [
            'recipient' => $recipient->email,
            'amount' => 1000,
            'transaction_pin' => '1234',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('wallets', ['user_id' => $sender->id, 'balance' => 4000]);
});

it('locks the pin after too many failed attempts', function () {
    $user = User::factory()->create();
    $service = app(TransactionPinService::class);
    $service->set($user, '1234');

    for ($i = 0; $i < TransactionPinService::MAX_ATTEMPTS; $i++) {
        $service->verify($user, '0000');
    }

    expect($service->verify($user, '1234')['status'])->toBe('locked');
});

it('removes the pin with the current pin', function () {
    $user = User::factory()->create();
    app(TransactionPinService::class)->set($user, '1234');

    $this->actingAs($user, 'api')
        ->postJson('/api/transaction-pin/remove', ['pin' => '1234'])
        ->assertStatus(200);

    expect($user->fresh()->hasTransactionPin())->toBeFalse();
});
