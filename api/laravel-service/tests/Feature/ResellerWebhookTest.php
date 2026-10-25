<?php

use App\Models\ResellerWebhook;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake();
});

it('allows authenticated users to create and list reseller webhooks', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 10000, 'currency' => 'NGN']);

    $create = $this->actingAs($user, 'api')
        ->postJson('/api/reseller/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['order.completed'],
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect($create->json('data.secret'))->not->toBeNull()
        ->and(ResellerWebhook::where('user_id', $user->id)->count())->toBe(1);

    $this->actingAs($user, 'api')
        ->getJson('/api/reseller/webhooks')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

it('deletes a reseller webhook', function () {
    $user = User::factory()->create();
    $webhook = ResellerWebhook::create([
        'user_id' => $user->id,
        'url' => 'https://example.com/hook',
        'secret' => 'abc123',
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson("/api/reseller/webhooks/{$webhook->id}")
        ->assertStatus(200)
        ->assertJsonPath('message', 'Webhook removed');

    $this->assertDatabaseMissing('reseller_webhooks', ['id' => $webhook->id]);
});

it('validates webhook url', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/reseller/webhooks', ['url' => 'not-a-url'])
        ->assertStatus(422);
});
