<?php

use App\Models\ApiKey;
use App\Models\IdempotentRequest;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
    FakeProvider::$verifyCalls = [];
    config([
        'aggregators.providers.fake' => [
            'class' => FakeProvider::class,
            'label' => 'Fake',
            'enabled' => true,
            'fake_mode' => 'success',
        ],
        'aggregators.routing' => array_fill_keys(['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'], ['fake']),
    ]);
});

function resellerAccount(): array
{
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 50000, 'currency' => 'NGN']);
    $issued = ApiKey::issue($user->id, 'Test reseller');

    return ['user' => $user, 'key' => $issued['api_key']];
}

it('rejects requests without an API key', function () {
    $this->getJson('/api/v1/reseller/transactions?reference=XYZ')->assertStatus(401);
});

it('rejects an invalid or deactivated API key', function () {
    ['user' => $reseller] = resellerAccount();
    $dead = ApiKey::issue($reseller->id);
    ApiKey::find($dead['api_key_id'])->update(['is_active' => false]);

    $this->withHeader('Authorization', 'Bearer '.$dead['api_key'])
        ->getJson('/api/v1/reseller/transactions?reference=XYZ')
        ->assertStatus(401);

    $this->withHeader('X-API-Key', 'viva_wrongkey_0000')
        ->getJson('/api/v1/reseller/transactions?reference=XYZ')
        ->assertStatus(401);
});

it('requires the Idempotency-Key header on purchases', function () {
    ['key' => $key] = resellerAccount();

    $this->withHeader('Authorization', 'Bearer '.$key)
        ->postJson('/api/v1/reseller/purchase/data', ['amount' => 1000, 'phone_number' => '08031234567'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Idempotency-Key header is required');
});

it('executes a purchase once and replays the same result for the same idempotency key', function () {
    ['user' => $reseller, 'key' => $key] = resellerAccount();

    $headers = [
        'Authorization' => 'Bearer '.$key,
        'Idempotency-Key' => '4322c93f-1b3a-4f2e-9123-000000000001',
    ];

    $first = $this->withHeaders($headers)
        ->postJson('/api/v1/reseller/purchase/data', ['amount' => 1000, 'phone_number' => '08031234567', 'network' => 'mtn', 'plan' => 'gift-1gb'])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('status', 'successful')
        ->assertJsonPath('meta.idempotentReplayed', false);

    $reference = $first->json('transaction.reference');

    $second = $this->withHeaders($headers)
        ->postJson('/api/v1/reseller/purchase/data', ['amount' => 1000, 'phone_number' => '08031234567', 'network' => 'mtn', 'plan' => 'gift-1gb'])
        ->assertStatus(200)
        ->assertJsonPath('meta.idempotentReplayed', true)
        ->assertJsonPath('transaction.reference', $reference);

    expect(FakeProvider::$calls)->toHaveCount(1)
        ->and($second->json('transaction.reference'))->not->toBeNull();

    $this->assertDatabaseCount('transactions', 1)
        ->assertDatabaseHas('wallets', ['user_id' => $reseller->id, 'balance' => 49000]);
});

it('returns a conflict while an identical request is in flight', function () {
    ['user' => $reseller, 'key' => $key] = resellerAccount();

    IdempotentRequest::create([
        'user_id' => $reseller->id,
        'idempotency_key' => 'in-flight-key',
        'status' => 'pending',
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$key,
        'Idempotency-Key' => 'in-flight-key',
    ])->postJson('/api/v1/reseller/purchase/data', ['amount' => 1000, 'phone_number' => '08031234567'])
        ->assertStatus(409);
});

it('rejects unsupported categories', function () {
    ['key' => $key] = resellerAccount();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$key,
        'Idempotency-Key' => 'cat-key',
    ])->postJson('/api/v1/reseller/purchase/bitcoin', ['amount' => 1000])
        ->assertStatus(422);
});

it('validates the amount on purchases', function () {
    ['key' => $key] = resellerAccount();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$key,
        'Idempotency-Key' => 'amount-key',
    ])->postJson('/api/v1/reseller/purchase/data', ['amount' => 0, 'phone_number' => '08031234567'])
        ->assertStatus(422);
});

it('returns transaction status by reference and requery resolves pending orders', function () {
    ['user' => $reseller, 'key' => $key] = resellerAccount();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$key,
        'Idempotency-Key' => 'status-key',
    ])->postJson('/api/v1/reseller/purchase/data', ['amount' => 1000, 'phone_number' => '08031234567', 'network' => 'mtn', 'plan' => 'gift-1gb'])
        ->assertStatus(200);

    $reference = $this->withHeaders([
        'Authorization' => 'Bearer '.$key,
        'Idempotency-Key' => 'status-key',
    ])->postJson('/api/v1/reseller/purchase/data', ['amount' => 1000, 'phone_number' => '08031234567', 'network' => 'mtn', 'plan' => 'gift-1gb'])
        ->json('transaction.reference');

    $this->withHeader('Authorization', 'Bearer '.$key)
        ->getJson('/api/v1/reseller/transactions?reference='.$reference)
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.category', 'data');
});

it('returns 404 for an unknown reference', function () {
    ['key' => $key] = resellerAccount();

    $this->withHeader('Authorization', 'Bearer '.$key)
        ->getJson('/api/v1/reseller/transactions?reference=UNKNOWN-REF')
        ->assertStatus(404);
});

it('rejects expired API keys', function () {
    ['user' => $reseller] = resellerAccount();
    $issued = ApiKey::issue($reseller->id, 'Expired');
    ApiKey::find($issued['api_key_id'])->update(['expires_at' => now()->subDay()]);

    $this->withHeader('Authorization', 'Bearer '.$issued['api_key'])
        ->getJson('/api/v1/reseller/transactions?reference=XYZ')
        ->assertStatus(401);
});

it('allows admins to issue and revoke reseller keys', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $reseller = User::factory()->create();

    $issue = $this->actingAs($admin, 'api')
        ->postJson('/api/admin/reseller-keys', ['user_id' => $reseller->id, 'name' => 'Reseller A'])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $keyId = $issue->json('data.api_key_id');

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/admin/reseller-keys/{$keyId}")
        ->assertStatus(200)
        ->assertJsonPath('message', 'API key revoked');

    $this->assertFalse((bool) ApiKey::find($keyId)->is_active);
});
