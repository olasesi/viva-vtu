<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a user to create and reply to a ticket', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'NGN']);

    $create = $this->actingAs($user, 'api')
        ->postJson('/api/tickets', [
            'subject' => 'Failed purchase',
            'category' => 'transaction',
            'priority' => 'high',
            'message' => 'My data purchase failed but I was debited',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $ticketId = $create->json('data.id');

    $this->actingAs($user, 'api')
        ->getJson("/api/tickets/{$ticketId}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->actingAs($user, 'api')
        ->postJson("/api/tickets/{$ticketId}/reply", ['message' => 'Please check urgently'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('tickets', ['id' => $ticketId, 'user_id' => $user->id]);
    $this->assertDatabaseCount('ticket_messages', 2);
});

it('allows admins to view and reply to tickets', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $ticket = Ticket::create([
        'user_id' => $user->id,
        'ticket_number' => Ticket::generateTicketNumber(),
        'subject' => 'Wallet issue',
        'category' => 'wallet',
        'priority' => 'medium',
        'status' => 'open',
        'last_reply_at' => now(),
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/admin/tickets')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->actingAs($admin, 'api')
        ->postJson("/api/admin/tickets/{$ticket->id}/reply", ['message' => 'We are investigating', 'status' => 'in_progress'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $ticket->refresh();
    expect($ticket->status)->toBe('in_progress')->and($ticket->assigned_to)->toBe($admin->id);
});

it('prevents replying to closed tickets', function () {
    $user = User::factory()->create();

    $ticket = Ticket::create([
        'user_id' => $user->id,
        'ticket_number' => Ticket::generateTicketNumber(),
        'subject' => 'Test',
        'category' => 'other',
        'priority' => 'low',
        'status' => 'closed',
        'last_reply_at' => now(),
    ]);

    $this->actingAs($user, 'api')
        ->postJson("/api/tickets/{$ticket->id}/reply", ['message' => 'Hello'])
        ->assertStatus(422);
});

it('validates ticket creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/tickets', ['subject' => 'Test'])
        ->assertStatus(422);
});
