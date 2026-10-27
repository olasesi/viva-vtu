<?php

namespace App\Services;

use App\Models\RecurringPurchase;
use App\Notifications\TransactionUpdate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecurringPurchaseService
{
    public function __construct(protected TransactionService $transactionService) {}

    public function computeNextRunAt(
        CarbonInterface $from,
        string $frequency,
        int $interval = 1,
        ?int $dayOfMonth = null,
    ): Carbon {
        $base = Carbon::instance($from);
        $interval = max(1, $interval);

        return match ($frequency) {
            'daily' => $base->copy()->addDays($interval),
            'weekly' => $base->copy()->addWeeks($interval),
            'monthly' => $this->addMonths($base, $interval, $dayOfMonth),
            default => $base->copy()->addMonthNoOverflow(),
        };
    }

    public function create(int $userId, array $data): RecurringPurchase
    {
        $startAt = ! empty($data['start_at'])
            ? Carbon::parse($data['start_at'])
            : now();

        $frequency = $data['frequency'] ?? 'monthly';
        $interval = (int) ($data['interval'] ?? 1);
        $dayOfMonth = isset($data['day_of_month']) ? (int) $data['day_of_month'] : null;

        $nextRunAt = ! empty($data['start_at'])
            ? $startAt
            : $this->computeNextRunAt($startAt, $frequency, $interval, $dayOfMonth);

        return RecurringPurchase::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'category' => $data['category'],
            'payload' => $data['payload'],
            'amount' => $data['payload']['amount'],
            'frequency' => $frequency,
            'interval' => $interval,
            'day_of_month' => $dayOfMonth,
            'status' => 'active',
            'next_run_at' => $nextRunAt,
        ]);
    }

    public function reschedule(RecurringPurchase $purchase): void
    {
        $purchase->update([
            'next_run_at' => $this->computeNextRunAt(
                now(),
                $purchase->frequency,
                $purchase->interval,
                $purchase->day_of_month,
            ),
        ]);
    }

    /**
     * Execute a single due recurring purchase while holding a per-purchase lock.
     *
     * @return array{status: string, message: string}
     */
    public function process(RecurringPurchase $purchase, bool $force = false): array
    {
        $lock = Cache::lock(
            'recurring-purchase:'.$purchase->id,
            (int) config('recurring.lock_seconds', 120),
        );

        if (! $lock->get()) {
            return ['status' => 'locked', 'message' => 'Another run is in progress'];
        }

        try {
            $purchase->refresh();

            if (! $force && $purchase->status !== 'active') {
                return ['status' => 'skipped', 'message' => 'Purchase is not active'];
            }

            if ($purchase->status === 'cancelled') {
                return ['status' => 'skipped', 'message' => 'Purchase has been cancelled'];
            }

            if (! $force && $purchase->next_run_at && $purchase->next_run_at->isFuture()) {
                return ['status' => 'skipped', 'message' => 'Purchase is not due yet'];
            }

            $result = $this->transactionService->execute(
                $purchase->category,
                $purchase->user_id,
                $purchase->payload,
            );

            $successful = in_array($result['status'], ['successful', 'processing'], true);

            return $successful
                ? $this->recordSuccess($purchase, $result)
                : $this->recordFailure($purchase, $result['message'] ?? 'Recurring purchase failed');
        } catch (Throwable $exception) {
            Log::error('Recurring purchase failed with an exception', [
                'recurring_purchase_id' => $purchase->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->recordFailure($purchase, $exception->getMessage());
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function runDue(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('recurring.batch_size', 50);

        $purchases = RecurringPurchase::query()
            ->due()
            ->orderBy('next_run_at')
            ->limit($limit)
            ->get();

        $succeeded = 0;
        $failed = 0;

        foreach ($purchases as $purchase) {
            $outcome = $this->process($purchase);

            if ($outcome['status'] === 'successful' || $outcome['status'] === 'processing') {
                $succeeded++;
            } elseif ($outcome['status'] === 'failed') {
                $failed++;
            }
        }

        return [
            'processed' => $purchases->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    protected function recordSuccess(RecurringPurchase $purchase, array $result): array
    {
        $purchase->update([
            'last_run_at' => now(),
            'last_status' => $result['status'],
            'last_error' => null,
            'failure_count' => 0,
            'run_count' => $purchase->run_count + 1,
            'next_run_at' => $this->computeNextRunAt(
                now(),
                $purchase->frequency,
                $purchase->interval,
                $purchase->day_of_month,
            ),
        ]);

        return ['status' => $result['status'], 'message' => $result['message'] ?? 'Executed'];
    }

    protected function recordFailure(RecurringPurchase $purchase, string $message): array
    {
        $failureCount = $purchase->failure_count + 1;
        $maxFailures = (int) config('recurring.max_failures', 3);

        $attributes = [
            'last_run_at' => now(),
            'last_status' => 'failed',
            'last_error' => $message,
            'failure_count' => $failureCount,
            'run_count' => $purchase->run_count + 1,
            'next_run_at' => now()->addMinutes(
                (int) config('recurring.retry_delay_minutes', 60),
            ),
        ];

        if ($failureCount >= $maxFailures) {
            $attributes['status'] = 'paused';
        }

        $purchase->update($attributes);

        $purchase->user?->notify(new TransactionUpdate(
            'Recurring Purchase Failed',
            "{$purchase->name} could not be completed: {$message}",
            [
                'type' => 'recurring',
                'amount' => (float) $purchase->amount,
                'status' => 'failed',
            ],
        ));

        return ['status' => 'failed', 'message' => $message];
    }

    protected function addMonths(Carbon $base, int $interval, ?int $dayOfMonth): Carbon
    {
        $next = $base->copy()->addMonthsNoOverflow($interval);

        if ($dayOfMonth !== null) {
            $dayOfMonth = max(1, min(31, $dayOfMonth));
            $next = $next->setDate(
                $next->year,
                $next->month,
                min($dayOfMonth, $next->daysInMonth),
            );
        }

        return $next;
    }
}
