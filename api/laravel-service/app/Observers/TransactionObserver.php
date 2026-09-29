<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\CommissionService;
use App\Services\ReceiptService;

class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        $original = $transaction->getOriginal('status');
        $current = $transaction->status;

        if ($original === $current) {
            return;
        }

        $service = app(ReceiptService::class);

        if ($current === 'successful') {
            if ($transaction->type === 'debit') {
                $service->send($transaction, 'success');

                app(CommissionService::class)->creditReferralBonusFor($transaction);
            } elseif ($transaction->category === 'wallet_fund') {
                $service->send($transaction, 'funded');
            } elseif ($transaction->category === 'transfer') {
                $service->send($transaction, 'received');
            }

            return;
        }

        if (
            $current === 'failed'
            && $transaction->type === 'debit'
            && $transaction->reversed_at !== null
        ) {
            $service->send($transaction, 'refund');
        }
    }

    public function created(Transaction $transaction): void
    {
        if ($transaction->status !== 'successful') {
            return;
        }

        $service = app(ReceiptService::class);

        if ($transaction->type === 'debit' && $transaction->category === 'transfer') {
            $service->send($transaction, 'success');
        } elseif ($transaction->type === 'credit') {
            if ($transaction->category === 'transfer' || $transaction->category === 'referral') {
                $service->send($transaction, 'received');
            } elseif ($transaction->category === 'wallet_fund') {
                $service->send($transaction, 'funded');
            }
        }
    }
}
