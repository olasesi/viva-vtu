<?php

use App\Models\Dispute;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDisputableTransaction(User $user, array $overrides = []): Transaction
{
    $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        ['balance' => 5000, 'currency' => 'NGN'],
    );

    return Transaction::create(array_merge([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'reference' => 'TXN-'.uniqid(),
        'type' => 'debit',
        'category' => 'airtime',
        'amount' => 500,
        'status' => 'failed',
    ], $overrides));
}

it('requires authentication to list disputes', function () {
    $this->getJson('/api/disputes')->assertStatus(401);
});

it('lists only the authenticated users disputes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Dispute::create([
        'user_id' => $user->id,
        'transaction_id' => makeDisputableTransaction($user)->id,
        'reference' => 'TXN-1',
        'reason' => 'not_received',
        'description' => 'Airtime not delivered',
        'status' => 'open',
    ]);

    Dispute::create([
        'user_id' => $other->id,
        'transaction_id' => makeDisputableTransaction($other)->id,
        'reference' => 'TXN-2',
        'reason' => 'other',
        'description' => 'Other issue',
        'status' => 'open',
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/disputes')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.data');
});

it('creates a dispute for a transaction owned by the user', function () {
    $user = User::factory()->create();
    $transaction = makeDisputableTransaction($user);

    $this->actingAs($user, 'api')
        ->postJson('/api/disputes', [
            'transaction_id' => $transaction->id,
            'reason' => 'debited_but_failed',
            'description' => 'I was debited but the purchase failed.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('disputes', [
        'user_id' => $user->id,
        'transaction_id' => $transaction->id,
        'reason' => 'debited_but_failed',
        'status' => 'open',
    ]);
});

it('validates the dispute payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/disputes', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['transaction_id', 'reason', 'description']);
});

it('does not allow disputing another users transaction', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $transaction = makeDisputableTransaction($other);

    $this->actingAs($user, 'api')
        ->postJson('/api/disputes', [
            'transaction_id' => $transaction->id,
            'reason' => 'not_received',
            'description' => 'Not mine',
        ])
        ->assertStatus(404);
});

it('prevents duplicate active disputes for the same transaction', function () {
    $user = User::factory()->create();
    $transaction = makeDisputableTransaction($user);

    $payload = [
        'transaction_id' => $transaction->id,
        'reason' => 'not_received',
        'description' => 'Not received',
    ];

    $this->actingAs($user, 'api')->postJson('/api/disputes', $payload)->assertStatus(201);

    $this->actingAs($user, 'api')
        ->postJson('/api/disputes', $payload)
        ->assertStatus(422);
});

it('shows a dispute owned by the user', function () {
    $user = User::factory()->create();
    $transaction = makeDisputableTransaction($user);
    $dispute = Dispute::create([
        'user_id' => $user->id,
        'transaction_id' => $transaction->id,
        'reference' => $transaction->reference,
        'reason' => 'wrong_amount',
        'description' => 'Wrong amount charged',
        'status' => 'open',
    ]);

    $this->actingAs($user, 'api')
        ->getJson("/api/disputes/{$dispute->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $dispute->id);
});

it('hides another users dispute', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $transaction = makeDisputableTransaction($other);
    $dispute = Dispute::create([
        'user_id' => $other->id,
        'transaction_id' => $transaction->id,
        'reference' => $transaction->reference,
        'reason' => 'other',
        'description' => 'Other',
        'status' => 'open',
    ]);

    $this->actingAs($user, 'api')
        ->getJson("/api/disputes/{$dispute->id}")
        ->assertStatus(404);
});

it('lets an admin list and resolve disputes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $transaction = makeDisputableTransaction($user);
    $dispute = Dispute::create([
        'user_id' => $user->id,
        'transaction_id' => $transaction->id,
        'reference' => $transaction->reference,
        'reason' => 'not_received',
        'description' => 'Not received',
        'status' => 'open',
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/admin/disputes')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/admin/disputes/{$dispute->id}", [
            'status' => 'resolved',
            'admin_note' => 'Refunded to wallet',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

    $dispute->refresh();

    expect($dispute->status)->toBe('resolved')
        ->and($dispute->resolved_by)->toBe($admin->id)
        ->and($dispute->resolved_at)->not->toBeNull();
});

it('blocks non-admin users from the admin dispute endpoints', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/admin/disputes')
        ->assertStatus(403);
});
