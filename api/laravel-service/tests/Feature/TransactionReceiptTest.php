<?php

use App\Mail\TransactionReceipt;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SettingService;
use App\Services\TransactionService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    FakeProvider::$calls = [];
    FakeProvider::$verifyCalls = [];
    config(['receipts.email.enabled' => true]);
});

function receiptRouteFakeProvider(string $mode): void
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

function receiptConfigureSender(): void
{
    $settings = app(SettingService::class);
    $settings->set('email', 'mail_from_address', 'no-reply@vivavtu.com');
    $settings->set('email', 'mail_from_name', 'VIVAVTU');
}

function receiptMakePurchase(int $userId, array $params = []): array
{
    return app(TransactionService::class)->execute('data', $userId, array_merge([
        'phone_number' => '08031234567',
        'amount' => 1000,
        'network' => 'mtn',
        'plan' => 'gift-1gb',
    ], $params));
}

it('sends a purchase receipt when a debit settles successfully', function () {
    receiptConfigureSender();
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    receiptRouteFakeProvider('success');

    $result = receiptMakePurchase($user->id);

    expect($result['status'])->toBe('successful');

    Mail::assertSent(TransactionReceipt::class, function (TransactionReceipt $mail) use ($user) {
        return $mail->event === 'success'
            && $mail->user->id === $user->id
            && $mail->hasTo($user->email);
    });
});

it('sends a refund receipt when a debit is reversed', function () {
    receiptConfigureSender();
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    receiptRouteFakeProvider('definitive');

    $result = receiptMakePurchase($user->id);

    expect($result['status'])->toBe('failed');

    Mail::assertSent(TransactionReceipt::class, function (TransactionReceipt $mail) use ($user) {
        return $mail->event === 'refund' && $mail->user->id === $user->id;
    });

    Mail::assertSentCount(1);
});

it('sends success and received receipts for a wallet transfer', function () {
    receiptConfigureSender();
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    Wallet::create(['user_id' => $sender->id, 'balance' => 5000, 'currency' => 'NGN']);

    app(WalletService::class)->transfer($sender->id, $recipient->id, 1500, 'TRX-REC-1');

    Mail::assertSent(TransactionReceipt::class, fn (TransactionReceipt $mail) => $mail->event === 'success' && $mail->user->id === $sender->id);
    Mail::assertSent(TransactionReceipt::class, fn (TransactionReceipt $mail) => $mail->event === 'received' && $mail->user->id === $recipient->id);
});

it('sends a funded receipt when a pending wallet funding credit settles', function () {
    receiptConfigureSender();
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'NGN']);

    $tx = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
        'reference' => 'FUND-REC-1',
        'description' => 'Wallet funding',
        'amount' => 2000,
        'status' => 'pending',
    ]);

    $tx->update(['status' => 'successful', 'completed_at' => now()]);

    Mail::assertSent(TransactionReceipt::class, function (TransactionReceipt $mail) use ($user) {
        return $mail->event === 'funded' && $mail->user->id === $user->id;
    });
});

it('does not email a receipt when the sender is not configured', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    receiptRouteFakeProvider('success');

    $result = receiptMakePurchase($user->id);

    expect($result['status'])->toBe('successful');

    Mail::assertNothingSent();
});

it('does not email a receipt when receipt emission is disabled', function () {
    receiptConfigureSender();
    config(['receipts.email.enabled' => false]);
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    receiptRouteFakeProvider('success');

    $result = receiptMakePurchase($user->id);

    expect($result['status'])->toBe('successful');

    Mail::assertNothingSent();
});

it('never emails receipts for reversal credit rows or failed pending purchases', function () {
    receiptConfigureSender();
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    receiptRouteFakeProvider('definitive');

    $result = receiptMakePurchase($user->id);

    expect($result['status'])->toBe('failed');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'type' => 'credit',
        'category' => 'reversal',
    ])->assertDatabaseMissing('transactions', [
        'user_id' => $user->id,
        'type' => 'credit',
        'category' => 'wallet_fund',
    ]);

    Mail::assertSentCount(1);
    Mail::assertSent(TransactionReceipt::class, fn (TransactionReceipt $mail) => $mail->event === 'refund');
});
