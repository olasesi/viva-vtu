<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an airtime-to-cash request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/service-requests/airtime-to-cash', [
            'network' => 'mtn',
            'phone_number' => '08012345678',
            'amount' => 2000,
            'bank_name' => 'GTBank',
            'account_number' => '0123456789',
            'account_name' => 'John Doe',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'type' => 'airtime_to_cash',
                'status' => 'pending',
                'amount' => 2000,
            ],
        ]);

    $this->assertDatabaseHas('service_requests', [
        'user_id' => $user->id,
        'type' => 'airtime_to_cash',
        'status' => 'pending',
        'amount' => 2000,
    ]);
});

it('validates airtime-to-cash fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/service-requests/airtime-to-cash', [
            'amount' => 10,
        ]);

    $response->assertStatus(422);
});

it('creates a bulk purchase request and computes the total', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/service-requests/bulk', [
            'items' => [
                ['type' => 'airtime', 'phone_number' => '08012345678', 'network' => 'mtn', 'amount' => 500],
                ['type' => 'data', 'phone_number' => '08098765432', 'network' => 'glo', 'amount' => 1500, 'plan' => 'gift-1gb'],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'type' => 'bulk',
                'status' => 'pending',
                'amount' => 2000,
            ],
        ]);

    $request = ServiceRequest::where('user_id', $user->id)->where('type', 'bulk')->first();

    expect($request)->not->toBeNull()
        ->and($request->payload['items'])->toHaveCount(2);
});

it('rejects bulk data items without a plan', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/service-requests/bulk', [
            'items' => [
                ['type' => 'data', 'phone_number' => '08098765432', 'network' => 'glo', 'amount' => 1500],
            ],
        ]);

    $response->assertStatus(422);
});

it('lists only the authenticated users requests', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    ServiceRequest::create([
        'user_id' => $user->id,
        'type' => 'airtime_to_cash',
        'reference' => 'A2C-OWNED',
        'status' => 'pending',
        'amount' => 1000,
        'payload' => [],
    ]);

    ServiceRequest::create([
        'user_id' => $other->id,
        'type' => 'bulk',
        'reference' => 'BULK-OTHER',
        'status' => 'pending',
        'amount' => 5000,
        'payload' => [],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/service-requests');

    $response->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('cancels a pending request', function () {
    $user = User::factory()->create();
    $request = ServiceRequest::create([
        'user_id' => $user->id,
        'type' => 'airtime_to_cash',
        'reference' => 'A2C-CANCEL',
        'status' => 'pending',
        'amount' => 1000,
        'payload' => [],
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson("/api/service-requests/{$request->id}/cancel");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'status' => 'cancelled',
            ],
        ]);
});

it('cannot cancel a non-pending request', function () {
    $user = User::factory()->create();
    $request = ServiceRequest::create([
        'user_id' => $user->id,
        'type' => 'airtime_to_cash',
        'reference' => 'A2C-DONE',
        'status' => 'completed',
        'amount' => 1000,
        'payload' => [],
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson("/api/service-requests/{$request->id}/cancel");

    $response->assertStatus(400);
});
