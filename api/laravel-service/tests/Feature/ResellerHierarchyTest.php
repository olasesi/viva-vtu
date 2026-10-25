<?php

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

function hierarchyPurchase(int $buyerId, array $params = []): array
{
    return app(TransactionService::class)->execute('data', $buyerId, array_merge([
        'phone_number' => '08031234567',
        'amount' => 10000,
        'network' => 'mtn',
        'plan' => 'gift-1gb',
    ], $params));
}

it('credits the reseller parent a margin on downline purchases', function () {
    $parent = User::factory()->create([
        'is_reseller' => true,
        'reseller_margin_rate' => 0.05,
    ]);
    Wallet::create(['user_id' => $parent->id, 'balance' => 0, 'currency' => 'NGN']);
    $downline = User::factory()->create(['reseller_parent_id' => $parent->id]);
    Wallet::create(['user_id' => $downline->id, 'balance' => 20000, 'currency' => 'NGN']);

    $result = hierarchyPurchase($downline->id);

    expect($result['status'])->toBe('successful');

    $this->assertDatabaseHas('wallets', [
        'user_id' => $parent->id,
        'balance' => 500,
    ])->assertDatabaseHas('transactions', [
        'user_id' => $parent->id,
        'type' => 'credit',
        'category' => 'referral',
        'amount' => 500,
        'status' => 'successful',
    ]);
});

it('does not credit margin when parent is not a reseller or rate is zero', function () {
    $parent = User::factory()->create(['is_reseller' => false, 'reseller_margin_rate' => 0]);
    $downline = User::factory()->create(['reseller_parent_id' => $parent->id]);
    Wallet::create(['user_id' => $downline->id, 'balance' => 20000, 'currency' => 'NGN']);

    hierarchyPurchase($downline->id);

    $this->assertDatabaseMissing('transactions', ['user_id' => $parent->id, 'category' => 'referral']);
});

it('exposes the reseller hierarchy to the user', function () {
    $parent = User::factory()->create(['first_name' => 'Mama', 'last_name' => 'Boss']);
    $user = User::factory()->create(['reseller_parent_id' => $parent->id]);
    $child = User::factory()->create(['reseller_parent_id' => $user->id, 'is_reseller' => true]);

    $this->actingAs($user, 'api')
        ->getJson('/api/reseller/hierarchy')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.parent.id', $parent->id)
        ->assertJsonPath('data.downlineCount', 1);
});

it('allows admins to assign a reseller to a parent with a margin rate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parent = User::factory()->create(['is_reseller' => true]);
    $reseller = User::factory()->create();

    $this->actingAs($admin, 'api')
        ->postJson('/api/admin/resellers/assign', [
            'reseller_id' => $reseller->id,
            'parent_id' => $parent->id,
            'margin_rate' => 0.03,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.marginRate', 0.03);

    $reseller->refresh();

    expect($reseller->reseller_parent_id)->toBe($parent->id)
        ->and($reseller->is_reseller)->toBeTrue()
        ->and($reseller->reseller_margin_rate)->toBe(0.03);
});

it('blocks non-admins from assigning resellers', function () {
    $user = User::factory()->create();
    $parent = User::factory()->create();
    $reseller = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/admin/resellers/assign', [
            'reseller_id' => $reseller->id,
            'parent_id' => $parent->id,
        ])
        ->assertStatus(403);
});
