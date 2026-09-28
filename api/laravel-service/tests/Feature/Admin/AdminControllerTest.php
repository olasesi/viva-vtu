<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminActor(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function regularActor(): User
{
    return User::factory()->create(['role' => 'user']);
}

function fundedUser(string $tag = '001'): array
{
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000.00,
        'currency' => 'NGN',
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => "FUND-ADMIN-{$tag}",
        'description' => 'Wallet funding',
        'amount' => 5000.00,
        'status' => 'successful',
        'provider' => 'paystack',
        'provider_reference' => "ps-{$tag}",
        'completed_at' => now(),
    ]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'category' => 'airtime',
        'reference' => "BUY-ADMIN-{$tag}",
        'description' => 'Airtime purchase',
        'amount' => 500.00,
        'status' => 'failed',
    ]);

    return [$user, $wallet];
}

it('requires authentication to list admin users', function () {
    $this->getJson('/api/admin/users')->assertStatus(401);
});

it('requires authentication to list admin transactions', function () {
    $this->getJson('/api/admin/transactions')->assertStatus(401);
});

it('requires authentication to view admin stats', function () {
    $this->getJson('/api/admin/stats')->assertStatus(401);
});

it('forbids non-admin users from listing admin users', function () {
    regularActor();

    $this->actingAs(regularActor(), 'api')
        ->getJson('/api/admin/users')
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Forbidden: administrator access required',
        ]);
});

it('lists admin users with camelCase payload and pagination meta', function () {
    $admin = adminActor();
    $user = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->actingAs($admin, 'api')
        ->getJson('/api/admin/users?page=1&limit=10');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.page', 1)
        ->assertJsonPath('data.limit', 10)
        ->assertJsonPath('data.totalPages', 1)
        ->assertJsonStructure([
            'data' => [
                'items' => [[
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'role',
                    'isVerified',
                    'isActive',
                    'createdAt',
                ]],
            ],
        ]);

    $jane = collect($response->json('data.items'))->firstWhere('email', 'jane@example.com');

    expect($jane)
        ->not->toBeNull()
        ->firstName->toBe('Jane')
        ->lastName->toBe('Doe');
});

it('searches admin users by email', function () {
    $admin = adminActor();
    User::factory()->create(['email' => 'alpha@example.com']);
    User::factory()->create(['email' => 'beta@example.com']);

    $response = $this->actingAs($admin, 'api')
        ->getJson('/api/admin/users?search=beta');

    $response->assertStatus(200)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.email', 'beta@example.com');
});

it('lists admin transactions with nested user and filters', function () {
    [$user] = fundedUser();

    $response = $this->actingAs(adminActor(), 'api')
        ->getJson('/api/admin/transactions?type=wallet_fund&status=successful&limit=10');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.totalPages', 1)
        ->assertJsonStructure([
            'data' => [
                'items' => [[
                    'id',
                    'type',
                    'reference',
                    'totalAmount',
                    'status',
                    'createdAt',
                    'user' => ['firstName', 'lastName'],
                ]],
            ],
        ])
        ->assertJsonPath('data.items.0.reference', 'FUND-ADMIN-001')
        ->assertJsonPath('data.items.0.totalAmount', 5000)
        ->assertJsonPath('data.items.0.type', 'wallet_fund')
        ->assertJsonPath('data.items.0.user.firstName', $user->first_name)
        ->assertJsonPath('data.items.0.user.lastName', $user->last_name);
});

it('searches admin transactions by reference', function () {
    fundedUser();
    adminActor();

    $response = $this->actingAs(adminActor(), 'api')
        ->getJson('/api/admin/transactions?search=BUY-ADMIN-001');

    $response->assertStatus(200)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.reference', 'BUY-ADMIN-001')
        ->assertJsonPath('data.items.0.type', 'airtime')
        ->assertJsonPath('data.items.0.status', 'failed');
});

it('returns admin stats aggregates', function () {
    fundedUser('AAA');
    fundedUser('BBB');

    $response = $this->actingAs(adminActor(), 'api')
        ->getJson('/api/admin/stats');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.totalUsers', 3)
        ->assertJsonPath('data.totalTransactions', 4)
        ->assertJsonPath('data.totalRevenue', 10000)
        ->assertJsonPath('data.successRate', 50)
        ->assertJsonPath('data.failedTransactions', 2)
        ->assertJsonPath('data.pendingTransactions', 0)
        ->assertJsonStructure([
            'data' => [
                'totalUsers',
                'totalTransactions',
                'totalRevenue',
                'successRate',
                'activeUsers',
                'pendingTransactions',
                'failedTransactions',
                'todayTransactions',
                'todayRevenue',
            ],
        ]);
});
