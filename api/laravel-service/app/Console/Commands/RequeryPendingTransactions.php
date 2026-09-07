<?php

namespace App\Console\Commands;

use App\Jobs\RequeryPendingTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;

class RequeryPendingTransactions extends Command
{
    protected $signature = 'vtu:requery-pending';

    protected $description = 'Requery and reconcile pending VTU transactions';

    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) config('aggregators.requery.reconcile_minutes', 15));

        $pending = Transaction::where('status', 'pending')
            ->whereNotNull('provider')
            ->where('created_at', '<=', $cutoff)
            ->limit(100)
            ->get();

        foreach ($pending as $transaction) {
            RequeryPendingTransaction::dispatch($transaction->id);
        }

        $this->info("Dispatched requery for {$pending->count()} pending transaction(s).");

        return self::SUCCESS;
    }
}
