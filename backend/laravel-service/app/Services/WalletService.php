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

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $userId,
                'balance' => 0,
                'currency' => 'NGN',
            ]);
        }

        return (float) $wallet->balance;
    }

    public function credit(int $userId, float $amount, string $reference, string $description = ''): bool
    {
        return DB::transaction(function () use ($userId, $amount, $reference, $description) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'balance' => 0,
                    'currency' => 'NGN',
                ]);
            }

            $existingTransaction = Transaction::where('reference', $reference)->first();
            if ($existingTransaction) {
                Log::warning('Duplicate credit attempt detected', [
                    'user_id' => $userId,
                    'reference' => $reference,
                ]);
                return false;
            }

            $newBalance = (float) $wallet->balance + $amount;

            $wallet->update(['balance' => $newBalance, 'updated_at' => now()]);

            Transaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'category' => 'wallet_fund',
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
        return DB::transaction(function () use ($userId, $amount, $reference, $description) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (!$wallet) {
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

    public function transfer(int $fromUserId, int $toUserId, float $amount): bool
    {
        return DB::transaction(function () use ($fromUserId, $toUserId, $amount) {
            if ($fromUserId === $toUserId) {
                Log::warning('Self-transfer attempt', ['user_id' => $fromUserId]);
                return false;
            }

            $fromWallet = Wallet::where('user_id', $fromUserId)->lockForUpdate()->first();
            $toWallet = Wallet::where('user_id', $toUserId)->lockForUpdate()->first();

            if (!$fromWallet) {
                Log::warning('Source wallet not found', ['user_id' => $fromUserId]);
                return false;
            }

            if (!$toWallet) {
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
                return false;
            }

            $transferRef = 'TRF-' . strtoupper(uniqid());

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
                'category' => 'wallet_fund',
                'reference' => $transferRef,
                'description' => "Transfer to user #{$toUserId}",
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            Transaction::create([
                'user_id' => $toUserId,
                'wallet_id' => $toWallet->id,
                'type' => 'credit',
                'category' => 'wallet_fund',
                'reference' => $transferRef,
                'description' => "Transfer from user #{$fromUserId}",
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            Log::info('Wallet transfer completed', [
                'from_user' => $fromUserId,
                'to_user' => $toUserId,
                'amount' => $amount,
                'reference' => $transferRef,
            ]);

            return true;
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
