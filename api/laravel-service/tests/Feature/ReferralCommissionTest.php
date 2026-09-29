<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
    FakeProvider::$verifyCalls = [];
    config([
        'commission.referral' => [
            'enabled' => true,
            'rate' => 0.01,
            'min_amount' => 1.0,
            'categories' => ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'],
        ],
    ]);
});

function referralRouteFakeProvider(string $mode): void
{
    config([
        'aggregators.providers.fake' => [
            'class' => FakeProvider::class,
            'label' => 'Fake',
            'enabled' => true,
            'fake_mode' => $mode,
        ],
        'aggregators.routing' => array_fill_keys(['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'], ['fake']),
    ]);
}

function referralPurchase(int $buyerId, array $params = []): array
{
    return app(TransactionService::class)->execute('data', $buyerId, array_merge([
        'phone_number' => '08031234567',
        'amount' => 5000,
        'network' => 'mtn',
        'plan' => 'gift-1gb',
    ], $params));
}

it('auto-issues a unique referral code when a user is created', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    expect($first->referral_code)->toMatch('/^VTU[A-Z0-9]{6}$/')
        ->and($second->referral_code)->not->toBe($first->referral_code)
        ->and(User::where('referral_code', $first->referral_code)->count())->toBe(1);
});

it('credits the referrer a commission on a successful referral purchase', function () {
    referralRouteFakeProvider('success');
    $referrer = User::factory()->create();
    Wallet::create(['user_id' => $referrer->id, 'balance' => 100, 'currency' => 'NGN']);
    $buyer = User::factory()->create(['referred_by' => $referrer->id]);
    Wallet::create(['user_id' => $buyer->id, 'balance' => 10000, 'currency' => 'NGN']);

    $result = referralPurchase($buyer->id);

    expect($result['status'])->toBe('successful');

    $this->assertDatabaseHas('wallets', [
        'user_id' => $referrer->id,
        'balance' => 150,
    ])->assertDatabaseHas('transactions', [
        'user_id' => $referrer->id,
        'type' => 'credit',
        'category' => 'referral',
        'amount' => 50,
        'status' => 'successful',
    ]);
});

it('does not credit a commission when commissions are disabled', function () {
    config(['commission.referral.enabled' => false]);
    referralRouteFakeProvider('success');
    $referrer = User::factory()->create();
    Wallet::create(['user_id' => $referrer->id, 'balance' => 100, 'currency' => 'NGN']);
    $buyer = User::factory()->create(['referred_by' => $referrer->id]);
    Wallet::create(['user_id' => $buyer->id, 'balance' => 10000, 'currency' => 'NGN']);

    referralPurchase($buyer->id);

    $this->assertDatabaseMissing('transactions', ['category' => 'referral'])
        ->assertDatabaseHas('wallets', ['user_id' => $referrer->id, 'balance' => 100]);
});

it('does not credit a commission below the minimum threshold', function () {
    referralRouteFakeProvider('success');
    $referrer = User::factory()->create();
    $buyer = User::factory()->create(['referred_by' => $referrer->id]);
    Wallet::create(['user_id' => $buyer->id, 'balance' => 10000, 'currency' => 'NGN']);

    referralPurchase($buyer->id, ['amount' => 40]);

    $this->assertDatabaseMissing('transactions', ['category' => 'referral']);
});

it('does not credit a commission when the referrer is inactive', function () {
    referralRouteFakeProvider('success');
    $referrer = User::factory()->create(['is_active' => false]);
    $buyer = User::factory()->create(['referred_by' => $referrer->id]);
    Wallet::create(['user_id' => $buyer->id, 'balance' => 10000, 'currency' => 'NGN']);

    referralPurchase($buyer->id);

    $this->assertDatabaseMissing('transactions', ['category' => 'referral']);
});

it('does not credit a commission on a refunded purchase', function () {
    referralRouteFakeProvider('definitive');
    $referrer = User::factory()->create();
    $buyer = User::factory()->create(['referred_by' => $referrer->id]);
    Wallet::create(['user_id' => $buyer->id, 'balance' => 10000, 'currency' => 'NGN']);

    $result = referralPurchase($buyer->id);

    expect($result['status'])->toBe('failed');

    $this->assertDatabaseMissing('transactions', ['category' => 'referral']);
});

it('never credits a commission on transfers or reversal rows', function () {
    config('commission.referral.enabled', true);
    $referrer = User::factory()->create();
    $buyer = User::factory()->create(['referred_by' => $referrer->id]);
    Wallet::create(['user_id' => $buyer->id, 'balance' => 5000, 'currency' => 'NGN']);
    $peer = User::factory()->create();
    Wallet::create(['user_id' => $peer->id, 'balance' => 0, 'currency' => 'NGN']);

    app(WalletService::class)->transfer($buyer->id, $peer->id, 1000, 'TRX-REF-1');

    $this->assertDatabaseMissing('transactions', ['category' => 'referral']);
});

it('exposes the referral code, link and earnings to the user', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'NGN']);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $user->wallet->id,
        'type' => 'credit',
        'category' => 'referral',
        'reference' => 'REF-EARN-1',
        'description' => 'Referral bonus on purchase X-TRX-1',
        'amount' => 75,
        'status' => 'successful',
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/referral')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', $user->referral_code)
        ->assertJsonPath('data.totalEarned', 75)
        ->assertJsonPath('data.referralsCount', 0);

    expect($response->json('data.link'))->toContain($user->referral_code);
});

it('requires authentication for the referral endpoint', function () {
    $this->getJson('/api/referral')->assertStatus(401);
});
