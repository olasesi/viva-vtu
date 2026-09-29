<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
});

function routePhoneValidationPurchasesThroughFakeProvider(string $mode): void
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

it('rejects an airtime purchase when the phone belongs to a different network', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/airtime', [
            'phone_number' => '08051234567',
            'amount' => 500,
            'network' => 'mtn',
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonFragment(['message' => 'Number 08051234567 looks like a Glo line but you selected MTN.']);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 5000,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'user_id' => $user->id,
        'category' => 'airtime',
    ]);
});

it('rejects a data purchase when the phone number is invalid', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/data', [
            'phone_number' => '12345',
            'amount' => 1000,
            'network' => 'mtn',
            'plan' => 'gift-1gb',
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid phone number. Expected a valid Nigerian mobile number.',
        ]);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'balance' => 5000,
    ]);
});

it('still completes a purchase when the phone prefix matches the network', function () {
    routePhoneValidationPurchasesThroughFakeProvider('success');

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/data', [
            'phone_number' => '08031234567',
            'amount' => 1000,
            'network' => 'mtn',
            'plan' => 'gift-1gb',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'data',
        'status' => 'successful',
    ]);
});

it('returns the detected network on the verify-phone endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/verify-phone', [
            'phone_number' => '08031234567',
            'network' => 'mtn',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.network', 'mtn')
        ->assertJsonPath('data.network_match', true)
        ->assertJsonPath('data.normalized', '08031234567');
});

it('flags a network mismatch on the verify-phone endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/verify-phone', [
            'phone_number' => '08051234567',
            'network' => 'mtn',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.network', 'glo')
        ->assertJsonPath('data.network_match', false);
});

it('rejects an invalid phone number on the verify-phone endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/verify-phone', [
            'phone_number' => '12345',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.network', null);
});

it('tolerates unknown prefixes on the verify-phone endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/purchase/verify-phone', [
            'phone_number' => '07001234567',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.network', null);
});

it('requires authentication on the verify-phone endpoint', function () {
    $response = $this->postJson('/api/purchase/verify-phone', [
        'phone_number' => '08031234567',
    ]);

    $response->assertStatus(401);
});
