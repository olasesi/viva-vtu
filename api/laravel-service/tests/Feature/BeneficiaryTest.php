<?php

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to manage beneficiaries', function () {
    $this->getJson('/api/beneficiaries')->assertStatus(401);
});

it('creates a beneficiary', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/beneficiaries', [
            'name' => 'Ada Friend',
            'type' => 'wallet',
            'identifier' => 'ada@example.com',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Ada Friend');

    $this->assertDatabaseHas('beneficiaries', [
        'user_id' => $user->id,
        'type' => 'wallet',
        'identifier' => 'ada@example.com',
    ]);
});

it('validates the beneficiary payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/beneficiaries', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'type', 'identifier']);
});

it('rejects an unsupported beneficiary type', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/beneficiaries', [
            'name' => 'Bad Type',
            'type' => 'crypto',
            'identifier' => 'abc',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it('prevents duplicate beneficiaries for the same user', function () {
    $user = User::factory()->create();

    $payload = [
        'name' => 'Ada',
        'type' => 'wallet',
        'identifier' => 'ada@example.com',
    ];

    $this->actingAs($user, 'api')->postJson('/api/beneficiaries', $payload)->assertStatus(201);

    $this->actingAs($user, 'api')
        ->postJson('/api/beneficiaries', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

it('lists only the authenticated users beneficiaries ordered by favorites', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Zulu',
        'type' => 'wallet',
        'identifier' => 'zulu@example.com',
    ]);
    Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Alpha',
        'type' => 'wallet',
        'identifier' => 'alpha@example.com',
        'is_favorite' => true,
    ]);
    Beneficiary::create([
        'user_id' => $other->id,
        'name' => 'Other',
        'type' => 'wallet',
        'identifier' => 'other@example.com',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/beneficiaries')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0.name'))->toBe('Alpha');
});

it('filters beneficiaries by type', function () {
    $user = User::factory()->create();

    Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Bank Person',
        'type' => 'bank',
        'identifier' => '0123456789',
        'bank_code' => '044',
    ]);
    Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Wallet Person',
        'type' => 'wallet',
        'identifier' => 'person@example.com',
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/beneficiaries?type=bank')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bank Person');
});

it('updates a beneficiary', function () {
    $user = User::factory()->create();
    $beneficiary = Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'type' => 'wallet',
        'identifier' => 'old@example.com',
    ]);

    $this->actingAs($user, 'api')
        ->patchJson("/api/beneficiaries/{$beneficiary->id}", ['name' => 'New Name'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'New Name');
});

it('does not let a user update another users beneficiary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $beneficiary = Beneficiary::create([
        'user_id' => $other->id,
        'name' => 'Theirs',
        'type' => 'wallet',
        'identifier' => 'theirs@example.com',
    ]);

    $this->actingAs($user, 'api')
        ->patchJson("/api/beneficiaries/{$beneficiary->id}", ['name' => 'Hijacked'])
        ->assertStatus(404);
});

it('toggles a beneficiary favorite', function () {
    $user = User::factory()->create();
    $beneficiary = Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Fav',
        'type' => 'wallet',
        'identifier' => 'fav@example.com',
    ]);

    $this->actingAs($user, 'api')
        ->postJson("/api/beneficiaries/{$beneficiary->id}/favorite")
        ->assertStatus(200)
        ->assertJsonPath('data.is_favorite', true);

    expect($beneficiary->fresh()->is_favorite)->toBeTrue();
});

it('deletes a beneficiary', function () {
    $user = User::factory()->create();
    $beneficiary = Beneficiary::create([
        'user_id' => $user->id,
        'name' => 'Delete Me',
        'type' => 'wallet',
        'identifier' => 'delete@example.com',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson("/api/beneficiaries/{$beneficiary->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('beneficiaries', ['id' => $beneficiary->id]);
});

it('does not let a user delete another users beneficiary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $beneficiary = Beneficiary::create([
        'user_id' => $other->id,
        'name' => 'Theirs',
        'type' => 'wallet',
        'identifier' => 'theirs@example.com',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson("/api/beneficiaries/{$beneficiary->id}")
        ->assertStatus(404);
});
