<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionUpdate;
use App\Services\CommissionService;
use App\Services\ReceiptService;
use App\Services\ResellerMarginService;
use App\Services\ResellerWebhookService;

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
                app(ResellerMarginService::class)->creditDownlineMargin($transaction);
                app(ResellerWebhookService::class)->dispatchForTransaction($transaction, 'order.completed');

                if ($transaction->user_id) {
                    $user = User::find($transaction->user_id);
                    if ($user) {
                        $user->notify(new TransactionUpdate(
                            'Transaction Successful',
                            "Your {$transaction->category} transaction of ₦{$transaction->amount} was successful.",
                            ['type' => 'transaction', 'transaction_id' => $transaction->id, 'reference' => $transaction->reference, 'amount' => (string) $transaction->amount, 'status' => 'successful']
                        ));
                    }
                }
            } elseif ($transaction->category === 'wallet_fund') {
                $service->send($transaction, 'funded');

                if ($transaction->user_id) {
                    $user = User::find($transaction->user_id);
                    if ($user) {
                        $user->notify(new TransactionUpdate(
                            'Wallet Funded',
                            "Your wallet was credited with ₦{$transaction->amount}.",
                            ['type' => 'wallet', 'transaction_id' => $transaction->id, 'reference' => $transaction->reference, 'amount' => (string) $transaction->amount, 'status' => 'successful']
                        ));
                    }
                }
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

            app(ResellerWebhookService::class)->dispatchForTransaction($transaction, 'order.failed');

            if ($transaction->user_id) {
                $user = User::find($transaction->user_id);
                if ($user) {
                    $user->notify(new TransactionUpdate(
                        'Transaction Failed - Refunded',
                        "Your {$transaction->category} transaction of ₦{$transaction->amount} failed and was refunded.",
                        ['type' => 'transaction', 'transaction_id' => $transaction->id, 'reference' => $transaction->reference, 'amount' => (string) $transaction->amount, 'status' => 'failed']
                    ));
                }
            }
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

            app(ResellerMarginService::class)->creditDownlineMargin($transaction);
            app(ResellerWebhookService::class)->dispatchForTransaction($transaction, 'order.completed');
        } elseif ($transaction->type === 'credit') {
            if ($transaction->category === 'transfer' || $transaction->category === 'referral') {
                $service->send($transaction, 'received');
            } elseif ($transaction->category === 'wallet_fund') {
                $service->send($transaction, 'funded');
            }
        }
    }
}
