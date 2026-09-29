<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    public function creditReferralBonusFor(Transaction $purchase): ?Transaction
    {
        if ($purchase->type !== 'debit' || $purchase->status !== 'successful') {
            return null;
        }

        $referralConfig = config('commission.referral', []);

        if (! ($referralConfig['enabled'] ?? true)) {
            return null;
        }

        if (! in_array($purchase->category, (array) ($referralConfig['categories'] ?? []), true)) {
            return null;
        }

        $buyer = $purchase->user;

        if (! $buyer || ! $buyer->referred_by) {
            return null;
        }

        $referrer = User::find($buyer->referred_by);

        if (! $referrer || ! $referrer->is_active) {
            return null;
        }

        $amount = round(((float) $purchase->amount) * (float) ($referralConfig['rate'] ?? 0.01), 2);

        if ($amount < (float) ($referralConfig['min_amount'] ?? 1.0)) {
            return null;
        }

        $credited = app(WalletService::class)->credit(
            $referrer->id,
            $amount,
            'REF-'.$purchase->reference,
            'Referral bonus on purchase '.$purchase->reference,
            'referral'
        );

        if (! $credited) {
            return null;
        }

        Log::info('Referral commission credited', [
            'referrer_id' => $referrer->id,
            'buyer_id' => $buyer->id,
            'purchase_reference' => $purchase->reference,
            'amount' => $amount,
        ]);

        return Transaction::where('reference', 'REF-'.$purchase->reference)->latest('id')->first();
    }
}
