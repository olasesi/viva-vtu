<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\TransactionUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to list notifications', function () {
    $this->getJson('/api/notifications')->assertStatus(401);
});

it('lists a users notifications with an unread count', function () {
    $user = User::factory()->create();

    $user->notify(new TransactionUpdate('Hello', 'First notification'));
    $user->notify(new TransactionUpdate('World', 'Second notification'));

    $this->actingAs($user, 'api')
        ->getJson('/api/notifications')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('unread_count', 2)
        ->assertJsonCount(2, 'data.data');
});

it('reports the unread count', function () {
    $user = User::factory()->create();

    $user->notify(new TransactionUpdate('Hello', 'Body'));

    $this->actingAs($user, 'api')
        ->getJson('/api/notifications/unread-count')
        ->assertStatus(200)
        ->assertJsonPath('unread_count', 1);
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $user->notify(new TransactionUpdate('Hello', 'Body'));

    $notification = $user->notifications()->first();

    $this->actingAs($user, 'api')
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('does not let a user mark another users notification as read', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $owner->notify(new TransactionUpdate('Hello', 'Body'));
    $notification = $owner->notifications()->first();

    $this->actingAs($other, 'api')
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(200);

    expect($owner->unreadNotifications()->count())->toBe(1);
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $user->notify(new TransactionUpdate('One', 'Body'));
    $user->notify(new TransactionUpdate('Two', 'Body'));

    $this->actingAs($user, 'api')
        ->postJson('/api/notifications/read-all')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('notifies a user when a debit transaction succeeds', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'reference' => 'TEST-NOTIFY-1',
        'type' => 'debit',
        'category' => 'airtime',
        'amount' => 500,
        'status' => 'pending',
    ]);

    $transaction->update(['status' => 'successful']);

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['title'])->toBe('Transaction Successful');
});

it('notifies a user when their wallet is funded', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'NGN']);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'reference' => 'TEST-NOTIFY-2',
        'type' => 'credit',
        'category' => 'wallet_fund',
        'amount' => 10000,
        'status' => 'pending',
    ]);

    $transaction->update(['status' => 'successful']);

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['title'])->toBe('Wallet Funded');
});
