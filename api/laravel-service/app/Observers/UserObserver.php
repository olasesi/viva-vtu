<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function creating(User $user): void
    {
        if (! $user->referral_code) {
            $user->referral_code = $this->uniqueCode();
        }
    }

    protected function uniqueCode(): string
    {
        do {
            $code = 'VTU'.substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 8)), 0, 6);
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
