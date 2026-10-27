<?php

namespace App\Console\Commands;

use App\Services\RecurringPurchaseService;
use Illuminate\Console\Command;

class RunRecurringPurchases extends Command
{
    protected $signature = 'recurring:purchases:run';

    protected $description = 'Execute all due recurring purchases';

    public function handle(RecurringPurchaseService $service): int
    {
        $result = $service->runDue();

        $this->info(sprintf(
            'Processed %d recurring purchase(s): %d succeeded, %d failed.',
            $result['processed'],
            $result['succeeded'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
