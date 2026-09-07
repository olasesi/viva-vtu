<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RequeryPendingTransaction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $transactionId) {}

    public function handle(TransactionService $service): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction || $transaction->status !== 'pending') {
            return;
        }

        $service->requery($transaction);
    }
}
