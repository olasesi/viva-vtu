<?php

namespace App\Services;

use App\Models\User;

class KycService
{
    public const LEVEL_NONE = 0;

    public const LEVEL_BVN = 1;

    public const LEVEL_NIN = 2;

    public function submit(User $user, string $bvn = '', string $nin = ''): User
    {
        if ($bvn !== '') {
            $user->kyc_bvn_hash = hash('sha256', $bvn);
            $user->kyc_bvn_last4 = substr($bvn, -4);
        }

        if ($nin !== '') {
            $user->kyc_nin_hash = hash('sha256', $nin);
            $user->kyc_nin_last4 = substr($nin, -4);
        }

        $level = static::LEVEL_NONE;

        if ($user->kyc_bvn_hash) {
            $level = static::LEVEL_BVN;
        }

        if ($user->kyc_nin_hash) {
            $level = static::LEVEL_NIN;
        }

        $previous = (int) $user->kyc_level;

        $user->kyc_level = max($previous, $level);

        if ($user->kyc_level >= static::LEVEL_BVN && ! $user->kyc_verified_at) {
            $user->kyc_verified_at = now();
        }

        $user->save();

        return $user;
    }
}
