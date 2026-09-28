<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'fund@example.com']);
    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'balance' => 0.00,
        'currency' => 'NGN',
    ]);
});

function pendingFlutterwaveFunding(User $user, Wallet $wallet, string $reference = 'VIVATU-FLW-CHECK001', float $amount = 2000.00): Transaction
{
    return Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => $reference,
        'description' => 'Wallet funding via Flutterwave',
        'amount' => $amount,
        'status' => 'pending',
        'provider' => 'flutterwave',
    ]);
}

it('initializes a flutterwave wallet fund from the camelCase payment method', function () {
    $this->mock(FlutterwaveService::class, function ($mock) {
        $mock->shouldReceive('initializePayment')
            ->once()
            ->with(2500.0, 'NGN', $this->user->email, Mockery::type('string'), Mockery::type('array'))
            ->andReturn([
                'status' => 'success',
                'message' => 'Hosted Payment',
                'data' => [
                    'link' => 'https://checkout.flutterwave.com/pay/vivatufw123',
                    'tx_ref' => 'VIVATU-FLW-ABC123',
                ],
            ]);
    });

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/wallet/fund', [
            'amount' => 2500,
            'paymentMethod' => 'flutterwave',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.authorization_url', 'https://checkout.flutterwave.com/pay/vivatufw123')
        ->assertJsonPath('data.reference', 'VIVATU-FLW-ABC123')
        ->assertJsonPath('data.payment_method', 'flutterwave');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'reference' => 'VIVATU-FLW-ABC123',
        'amount' => 2500.00,
        'status' => 'pending',
        'provider' => 'flutterwave',
    ]);
});

it('initializes a flutterwave wallet fund from the snake_case payment method', function () {
    $this->mock(FlutterwaveService::class, function ($mock) {
        $mock->shouldReceive('initializePayment')
            ->once()
            ->with(1500.0, 'NGN', $this->user->email, Mockery::type('string'), Mockery::type('array'))
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'link' => 'https://checkout.flutterwave.com/pay/vivatufw456',
                    'tx_ref' => 'VIVATU-FLW-DEF456',
                ],
            ]);
    });

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/wallet/fund', [
            'amount' => 1500,
            'payment_method' => 'flutterwave',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.reference', 'VIVATU-FLW-DEF456');
});

it('forbids an unsupported payment method', function () {
    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/wallet/fund', [
            'amount' => 1000,
            'paymentMethod' => 'bitcoin',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['paymentMethod']);
});

it('credits the wallet when a flutterwave payment verifies successfully', function () {
    $pending = pendingFlutterwaveFunding($this->user, $this->wallet);

    $this->mock(FlutterwaveService::class, function ($mock) {
        $mock->shouldReceive('verifyByReference')
            ->once()
            ->with('VIVATU-FLW-CHECK001')
            ->andReturn([
                'status' => 'success',
                'message' => 'Transaction fetched successfully',
                'data' => [
                    'id' => 12345,
                    'tx_ref' => 'VIVATU-FLW-CHECK001',
                    'amount' => 2500.00,
                    'currency' => 'NGN',
                    'status' => 'successful',
                ],
            ]);
    });

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/wallet/verify/VIVATU-FLW-CHECK001');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'balance' => 2500.00,
                'currency' => 'NGN',
            ],
        ]);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $this->user->id,
        'balance' => 2500.00,
    ]);

    $this->assertDatabaseHas('transactions', [
        'id' => $pending->id,
        'reference' => 'VIVATU-FLW-CHECK001',
        'status' => 'successful',
        'provider_reference' => 12345,
    ]);
});

it('returns 400 when the flutterwave payment was not successful', function () {
    pendingFlutterwaveFunding($this->user, $this->wallet);

    $this->mock(FlutterwaveService::class, function ($mock) {
        $mock->shouldReceive('verifyByReference')
            ->once()
            ->with('VIVATU-FLW-CHECK001')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'status' => 'failed',
                    'amount' => 2000.00,
                ],
            ]);
    });

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/wallet/verify/VIVATU-FLW-CHECK001');

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Payment not successful',
        ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'VIVATU-FLW-CHECK001',
        'status' => 'pending',
    ]);
});

it('returns 502 when the flutterwave provider is unreachable', function () {
    pendingFlutterwaveFunding($this->user, $this->wallet);

    $this->mock(FlutterwaveService::class, function ($mock) {
        $mock->shouldReceive('verifyByReference')
            ->once()
            ->with('VIVATU-FLW-CHECK001')
            ->andReturn(null);
    });

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/wallet/verify/VIVATU-FLW-CHECK001');

    $response->assertStatus(502);
});
