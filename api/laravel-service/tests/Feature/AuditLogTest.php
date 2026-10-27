<?php

use App\Models\Dispute;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('requires admin access to view audit logs', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/admin/audit-logs')
        ->assertStatus(403);
});

it('records an audit entry for admin mutations', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();

    $ticket = Ticket::create([
        'user_id' => $user->id,
        'ticket_number' => 'TKT-AUDIT-1',
        'subject' => 'Audit me',
        'category' => 'other',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/admin/tickets/{$ticket->id}", ['status' => 'resolved'])
        ->assertStatus(200);

    $activity = Activity::where('log_name', 'admin')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('patch')
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['method'])->toBe('PATCH')
        ->and($activity->properties['status'])->toBe(200);
});

it('does not record an audit entry for read-only admin requests', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/admin/users')
        ->assertStatus(200);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

it('redacts sensitive fields from the audit payload', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 1000, 'currency' => 'NGN']);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'reference' => 'AUDIT-TX-1',
        'type' => 'credit',
        'category' => 'wallet_fund',
        'amount' => 1000,
        'status' => 'pending',
    ]);

    $dispute = Dispute::create([
        'user_id' => $user->id,
        'transaction_id' => $transaction->id,
        'reference' => $transaction->reference,
        'reason' => 'other',
        'description' => 'Issue',
        'status' => 'open',
    ]);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/admin/disputes/{$dispute->id}", [
            'status' => 'resolved',
            'admin_note' => 'Done',
            'password' => 'super-secret',
        ])
        ->assertStatus(200);

    $activity = Activity::where('log_name', 'admin')->latest('id')->first();

    expect($activity->properties['payload']['password'])->toBe('[redacted]')
        ->and($activity->properties['payload']['status'])->toBe('resolved');
});

it('lists audit logs with pagination for admins', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    activity('admin')->causedBy($admin)->event('patch')->log('First');
    activity('admin')->causedBy($admin)->event('post')->log('Second');

    $this->actingAs($admin, 'api')
        ->getJson('/api/admin/audit-logs')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.data');
});

it('filters audit logs by event and log name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    activity('admin')->causedBy($admin)->event('patch')->log('A patch');
    activity('admin')->causedBy($admin)->event('post')->log('A post');
    activity('user')->causedBy($admin)->event('patch')->log('User patch');

    $this->actingAs($admin, 'api')
        ->getJson('/api/admin/audit-logs?log_name=admin&event=patch')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.description', 'A patch');
});

it('shows a single audit log entry', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $activity = activity('admin')->causedBy($admin)->event('delete')->log('Removed something');

    $this->actingAs($admin, 'api')
        ->getJson("/api/admin/audit-logs/{$activity->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $activity->id);
});

it('returns 404 for a missing audit log', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/admin/audit-logs/999999')
        ->assertStatus(404);
});
