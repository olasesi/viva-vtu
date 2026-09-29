<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function getBalance(int $userId): float
    {
        $wallet = Wallet::where('user_id', $userId)->first();

        if (! $wallet) {
            $wallet = Wallet::create([
                'user_id' => $userId,
                'balance' => 0,
                'currency' => 'NGN',
            ]);
        }

        return (float) $wallet->balance;
    }

    public function credit(int $userId, float $amount, string $reference, string $description = '', string $category = 'wallet_fund'): bool
    {
        return DB::transaction(function () use ($userId, $amount, $reference, $description, $category) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'balance' => 0,
                    'currency' => 'NGN',
                ]);
            }

            $existingTransaction = Transaction::where('reference', $reference)->first();
            if ($existingTransaction) {
                if ($existingTransaction->status === 'successful') {
                    Log::warning('Duplicate credit attempt detected', [
                        'user_id' => $userId,
                        'reference' => $reference,
                    ]);

                    return false;
                }

                $newBalance = (float) $wallet->balance + $amount;

                $wallet->update(['balance' => $newBalance, 'updated_at' => now()]);

                $existingTransaction->update([
                    'amount' => $amount,
                    'description' => $description,
                    'status' => 'successful',
                    'provider' => $existingTransaction->provider,
                    'completed_at' => now(),
                ]);

                Log::info('Pending wallet credit confirmed', [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'reference' => $reference,
                    'new_balance' => $newBalance,
                ]);

                return true;
            }

            $newBalance = (float) $wallet->balance + $amount;

            $wallet->update(['balance' => $newBalance, 'updated_at' => now()]);

            Transaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'category' => $category,
                'reference' => $reference,
                'description' => $description,
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            Log::info('Wallet credited', [
                'user_id' => $userId,
                'amount' => $amount,
                'reference' => $reference,
                'new_balance' => $newBalance,
            ]);

            return true;
        });
    }

    public function debit(int $userId, float $amount, string $reference, string $description = ''): bool
    {
        return DB::transaction(function () use ($userId, $amount, $reference) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (! $wallet) {
                Log::warning('Wallet not found for debit', ['user_id' => $userId]);

                return false;
            }

            if ((float) $wallet->balance < $amount) {
                Log::warning('Insufficient wallet balance', [
                    'user_id' => $userId,
                    'requested' => $amount,
                    'available' => $wallet->balance,
                ]);

                return false;
            }

            $existingTransaction = Transaction::where('reference', $reference)->first();
            if ($existingTransaction) {
                Log::warning('Duplicate debit attempt detected', [
                    'user_id' => $userId,
                    'reference' => $reference,
                ]);

                return false;
            }

            $newBalance = (float) $wallet->balance - $amount;

            $wallet->update(['balance' => $newBalance, 'updated_at' => now()]);

            Log::info('Wallet debited', [
                'user_id' => $userId,
                'amount' => $amount,
                'reference' => $reference,
                'new_balance' => $newBalance,
            ]);

            return true;
        });
    }

    public function transfer(int $fromUserId, int $toUserId, float $amount, string $reference): array
    {
        return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $reference) {
            if ($fromUserId === $toUserId) {
                Log::warning('Self-transfer attempt', ['user_id' => $fromUserId]);

                return ['status' => 'self_transfer'];
            }

            $fromWallet = Wallet::where('user_id', $fromUserId)->lockForUpdate()->first();
            $toWallet = Wallet::where('user_id', $toUserId)->lockForUpdate()->first();

            if (! $fromWallet) {
                Log::warning('Source wallet not found', ['user_id' => $fromUserId]);

                return ['status' => 'no_source_wallet'];
            }

            if (! $toWallet) {
                $toWallet = Wallet::create([
                    'user_id' => $toUserId,
                    'balance' => 0,
                    'currency' => 'NGN',
                ]);
            }

            if ((float) $fromWallet->balance < $amount) {
                Log::warning('Insufficient balance for transfer', [
                    'user_id' => $fromUserId,
                    'requested' => $amount,
                    'available' => $fromWallet->balance,
                ]);

                return ['status' => 'insufficient'];
            }

            $fromWallet->update([
                'balance' => (float) $fromWallet->balance - $amount,
                'updated_at' => now(),
            ]);

            $toWallet->update([
                'balance' => (float) $toWallet->balance + $amount,
                'updated_at' => now(),
            ]);

            Transaction::create([
                'user_id' => $fromUserId,
                'wallet_id' => $fromWallet->id,
                'type' => 'debit',
                'category' => 'transfer',
                'reference' => $reference,
                'description' => "Transfer to user #{$toUserId}",
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            Transaction::create([
                'user_id' => $toUserId,
                'wallet_id' => $toWallet->id,
                'type' => 'credit',
                'category' => 'transfer',
                'reference' => $reference.'-C',
                'description' => "Transfer from user #{$fromUserId}",
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            Log::info('Wallet transfer completed', [
                'from_user' => $fromUserId,
                'to_user' => $toUserId,
                'amount' => $amount,
                'reference' => $reference,
            ]);

            return [
                'status' => 'success',
                'reference' => $reference,
                'from_balance' => (float) $fromWallet->fresh()->balance,
                'to_balance' => (float) $toWallet->fresh()->balance,
            ];
        });
    }

    public function ensureWalletExists(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'currency' => 'NGN']
        );
    }
}
