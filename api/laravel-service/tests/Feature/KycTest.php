<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for KYC endpoints', function () {
    $this->getJson('/api/kyc')->assertStatus(401);
    $this->postJson('/api/kyc', ['bvn' => '12345678901'])->assertStatus(401);
});

it('records a BVN and upgrades to level 1', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/kyc', ['bvn' => '20345678901'])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.level', 1)
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.bvn', '*******8901');

    $user->refresh();

    expect($user->kyc_bvn_hash)->toBe(hash('sha256', '20345678901'))
        ->and($user->kyc_level)->toBe(1)
        ->and($user->kyc_verified_at)->not->toBeNull();
});

it('upgrades to level 2 once a NIN is added', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/kyc', ['bvn' => '20345678901'])
        ->assertJsonPath('data.level', 1);

    $this->actingAs($user, 'api')
        ->postJson('/api/kyc', ['nin' => '12345678901'])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.level', 2)
        ->assertJsonPath('data.nin', '*******8901');

    $user->refresh();

    expect($user->kyc_nin_hash)->toBe(hash('sha256', '12345678901'))
        ->and($user->kyc_level)->toBe(2)
        ->and($user->kyc_verified_at)->not->toBeNull();
});

it('rejects malformed BVN or NIN identifiers', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/kyc', ['bvn' => '1234'])
        ->assertStatus(422);

    $this->actingAs($user, 'api')
        ->postJson('/api/kyc', ['nin' => 'abcdefghijk'])
        ->assertStatus(422);
});

it('requires at least one identifier on submit', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/kyc', [])
        ->assertStatus(422);
});

it('exposes only masked identifiers and never the raw hashes', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api')->postJson('/api/kyc', ['bvn' => '20345678901']);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/kyc')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.level', 1);

    expect($response->getContent())->not->toContain('20345678901')->not->toContain(hash('sha256', '20345678901'));
});
