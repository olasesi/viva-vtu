<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class TransactionPinService
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    public function verify(User $user, string $pin): array
    {
        if (! $user->hasTransactionPin()) {
            return ['status' => 'not_set'];
        }

        $lockKey = "txn_pin_lockout:{$user->id}";

        if (Cache::has($lockKey)) {
            return ['status' => 'locked'];
        }

        if (! Hash::check($pin, $user->transaction_pin_hash)) {
            $attemptsKey = "txn_pin_attempts:{$user->id}";
            $attempts = (int) Cache::get($attemptsKey, 0) + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::put($lockKey, true, self::LOCKOUT_SECONDS);
                Cache::forget($attemptsKey);

                return ['status' => 'locked'];
            }

            Cache::put($attemptsKey, $attempts, self::LOCKOUT_SECONDS);

            return ['status' => 'invalid', 'remaining' => self::MAX_ATTEMPTS - $attempts];
        }

        Cache::forget("txn_pin_attempts:{$user->id}");
        Cache::forget($lockKey);

        return ['status' => 'valid'];
    }

    public function set(User $user, string $pin): void
    {
        $user->forceFill([
            'transaction_pin_hash' => Hash::make($pin),
            'transaction_pin_set_at' => now(),
        ])->save();
    }

    public function clear(User $user, string $currentPin): bool
    {
        $result = $this->verify($user, $currentPin);

        if ($result['status'] !== 'valid') {
            return false;
        }

        $user->forceFill([
            'transaction_pin_hash' => null,
            'transaction_pin_set_at' => null,
        ])->save();

        return true;
    }
}
