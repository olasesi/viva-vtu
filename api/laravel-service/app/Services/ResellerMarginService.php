<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ResellerMarginService
{
    public function creditDownlineMargin(Transaction $purchase): ?Transaction
    {
        if ($purchase->type !== 'debit' || $purchase->status !== 'successful') {
            return null;
        }

        if (! in_array($purchase->category, ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'], true)) {
            return null;
        }

        $buyer = $purchase->user;

        if (! $buyer || ! $buyer->reseller_parent_id) {
            return null;
        }

        $parent = User::find($buyer->reseller_parent_id);

        if (! $parent || ! $parent->is_active || ! $parent->is_reseller) {
            return null;
        }

        $rate = (float) ($parent->reseller_margin_rate ?? 0);

        if ($rate <= 0) {
            return null;
        }

        $amount = round(((float) $purchase->amount) * $rate, 2);

        if ($amount < 0.01) {
            return null;
        }

        $credited = app(WalletService::class)->credit(
            $parent->id,
            $amount,
            'MRG-'.$purchase->reference,
            'Reseller margin on downline purchase '.$purchase->reference,
            'referral'
        );

        if (! $credited) {
            return null;
        }

        Log::info('Reseller margin credited', [
            'parent_id' => $parent->id,
            'buyer_id' => $buyer->id,
            'purchase_reference' => $purchase->reference,
            'amount' => $amount,
            'rate' => $rate,
        ]);

        return Transaction::where('reference', 'MRG-'.$purchase->reference)->latest('id')->first();
    }
}
