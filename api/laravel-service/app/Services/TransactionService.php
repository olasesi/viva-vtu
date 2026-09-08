<?php

namespace App\Services;

use App\Jobs\RequeryPendingTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Providers\ProviderContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionService
{
    protected const PREFIXES = [
        'airtime' => 'AIR',
        'data' => 'DAT',
        'electricity' => 'ELEC',
        'cable' => 'CABLE',
        'education' => 'EDU',
        'streaming' => 'STRM',
    ];

    protected const METHOD_MAP = [
        'airtime' => 'purchaseAirtime',
        'data' => 'purchaseData',
        'electricity' => 'purchaseElectricity',
        'cable' => 'purchaseCable',
        'education' => 'purchaseExamPins',
        'streaming' => 'purchaseStreaming',
    ];

    public function __construct(
        protected WalletService $walletService,
        protected ProviderRouter $router,
    ) {}

    /**
     * Execute a utility purchase end-to-end with idempotency, provider
     * failover and automatic reversal on confirmed failure.
     *
     * @return array{status: string, success: bool, message: string, transaction: Transaction, data: array}
     */
    public function execute(string $category, int $userId, array $params): array
    {
        $amount = (float) $params['amount'];
        $reference = $this->reference($category);

        $reserved = $this->reserve($userId, $category, $reference, $params, $amount);

        if ($reserved['status'] === 'insufficient') {
            return [
                'status' => 'failed',
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ];
        }

        if ($reserved['status'] === 'existed') {
            return $this->responseFromExisting($reserved['transaction']);
        }

        $transaction = $reserved['transaction'];
        $providers = $this->router->providersFor($category);

        foreach ($providers as $slug => $provider) {
            $transaction->forceFill([
                'provider' => $slug,
                'attempts' => $transaction->attempts + 1,
            ])->save();

            $attempt = $this->callProvider($category, $provider, $params, $reference);
            $response = $attempt['response'];

            if ($attempt['definitive_error']) {
                $this->router->markFailure($slug);

                continue;
            }

            if ($provider->isSuccessful($response)) {
                $this->router->markSuccess($slug);

                $transaction->update([
                    'status' => 'successful',
                    'provider_reference' => $this->providerReference($response),
                    'completed_at' => now(),
                    'metadata' => $this->mergeMetadata($transaction, [
                        'provider_response' => $response,
                        'provider' => $slug,
                    ]),
                ]);

                return [
                    'status' => 'successful',
                    'success' => true,
                    'message' => $this->successMessage($category),
                    'transaction' => $transaction->fresh(),
                    'data' => $this->successData($category, $params, $response, $slug),
                ];
            }

            // Ambiguous outcome (timeout / network error / unknown status):
            // the order may or may not have landed, so we must NOT retry it on
            // another provider (would risk double delivery). Requery later.
            Log::warning('Ambiguous provider response, scheduling requery', [
                'reference' => $reference,
                'provider' => $slug,
                'response' => $response,
            ]);

            RequeryPendingTransaction::dispatch($transaction->id)
                ->delay(now()->addMinutes((int) config('aggregators.requery.retry_delay_minutes', 2)));

            $transaction->forceFill([
                'last_error' => $response['response_message'] ?? 'Provider response unknown',
            ])->save();

            return [
                'status' => 'processing',
                'success' => true,
                'message' => 'Transaction submitted. Confirming delivery...',
                'transaction' => $transaction->fresh(),
                'data' => $this->processingData($transaction->fresh()),
            ];
        }

        return $this->reverse($transaction, 'All providers failed for '.$category);
    }

    /**
     * Requery an in-flight transaction and resolve it (success or reversal).
     */
    public function requery(Transaction $transaction): void
    {
        if ($transaction->status !== 'pending' || ! $transaction->provider) {
            return;
        }

        if ($transaction->attempts >= (int) config('aggregators.requery.max_attempts', 5)) {
            $this->reverse($transaction, 'Requery exhausted after '.$transaction->attempts.' attempts');

            return;
        }

        $provider = $this->router->resolve($transaction->provider);

        if (! $provider) {
            $this->reverse($transaction, 'Provider '.$transaction->provider.' is no longer available');

            return;
        }

        $response = $provider->requery($transaction->reference);
        $code = $response['code'] ?? null;

        if ($provider->isSuccessful($response)) {
            $transaction->update([
                'status' => 'successful',
                'provider_reference' => $this->providerReference($response),
                'completed_at' => now(),
                'last_error' => null,
                'metadata' => $this->mergeMetadata($transaction, ['provider_response' => $response]),
            ]);

            return;
        }

        if ($code === '999' || $code === null) {
            $transaction->increment('attempts');
            $transaction->update(['last_error' => $response['response_message'] ?? 'Provider unreachable']);

            RequeryPendingTransaction::dispatch($transaction->id)
                ->delay(now()->addMinutes((int) config('aggregators.requery.retry_delay_minutes', 2)));

            return;
        }

        $this->reverse($transaction, 'Requery confirmed failure: '.($response['response_message'] ?? 'Unknown provider error'));
    }

    public function reverse(Transaction $transaction, string $reason): array
    {
        $reference = $transaction->reference;
        $reversalReference = 'REV-'.$reference;

        $credited = $this->walletService->credit(
            $transaction->user_id,
            (float) $transaction->amount,
            $reversalReference,
            'Reversal for failed '.$transaction->category.' purchase '.$reference
        );

        $transaction->update([
            'status' => 'failed',
            'last_error' => $reason,
            'reversed_at' => now(),
            'metadata' => $this->mergeMetadata($transaction, [
                'reversal_reference' => $reversalReference,
                'error' => $reason,
                'reversal_credited' => $credited,
            ]),
        ]);

        Log::warning('Transaction reversed automatically', [
            'reference' => $reference,
            'reason' => $reason,
            'reversal_reference' => $reversalReference,
        ]);

        return [
            'status' => 'failed',
            'success' => false,
            'message' => 'Purchase failed. '.$reason.'. Your wallet has been refunded.',
            'transaction' => $transaction->fresh(),
            'data' => [
                'reference' => $reference,
                'transaction_id' => $transaction->id,
            ],
        ];
    }

    public function verifyCustomer(string $category, array $params): array
    {
        $providers = $this->router->providersFor($category);

        if (empty($providers)) {
            $provider = $this->router->resolve('vtpass');

            if ($provider) {
                $providers = ['vtpass' => $provider];
            }
        }

        foreach ($providers as $provider) {
            $response = $provider->verifyCustomer($params);

            if ($response && $provider->isSuccessful($response)) {
                return ['success' => true, 'data' => $response['content'] ?? []];
            }

            if ($response && ($response['code'] ?? null) !== '999') {
                return [
                    'success' => false,
                    'message' => $response['response_message'] ?? 'Customer verification failed',
                ];
            }
        }

        return ['success' => false, 'message' => 'Customer verification service unavailable'];
    }

    protected function reserve(int $userId, string $category, string $reference, array $params, float $amount): array
    {
        return DB::transaction(function () use ($userId, $category, $reference, $params, $amount) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = Wallet::create(['user_id' => $userId, 'balance' => 0, 'currency' => 'NGN']);
            }

            if ((float) $wallet->balance < $amount) {
                return ['status' => 'insufficient'];
            }

            $existing = Transaction::where('reference', $reference)->first();

            if ($existing) {
                return ['status' => 'existed', 'transaction' => $existing];
            }

            $transaction = Transaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'category' => $category,
                'reference' => $reference,
                'description' => $this->description($category, $params),
                'amount' => $amount,
                'status' => 'pending',
                'metadata' => $params,
            ]);

            $wallet->update([
                'balance' => (float) $wallet->balance - $amount,
                'updated_at' => now(),
            ]);

            Log::info('Wallet reserved for purchase', [
                'user_id' => $userId,
                'reference' => $reference,
                'amount' => $amount,
                'category' => $category,
            ]);

            return ['status' => 'created', 'transaction' => $transaction];
        });
    }

    /**
     * @return array{response: array, definitive_error: bool}
     */
    protected function callProvider(string $category, ProviderContract $provider, array $params, string $reference): array
    {
        $payload = array_merge($params, ['request_id' => $reference]);

        $response = $provider->{self::METHOD_MAP[$category] ?? 'purchase'.ucfirst($category)}($payload);

        $code = $response['code'] ?? '999';
        $definitiveError = $code !== '000' && $code !== '999';

        return [
            'response' => $response,
            'definitive_error' => $definitiveError,
        ];
    }

    protected function reference(string $category): string
    {
        $prefix = self::PREFIXES[$category] ?? strtoupper(Str::substr($category, 0, 4));

        return $prefix.'-'.strtoupper(Str::random(12));
    }

    protected function description(string $category, array $params): string
    {
        return match ($category) {
            'airtime' => "Airtime purchase for {$params['phone_number']}",
            'data' => "Data purchase for {$params['phone_number']}",
            'electricity' => "Electricity purchase for meter {$params['meter_number']}",
            'cable' => "Cable subscription for {$params['smartcard_number']}",
            'education' => 'Exam pin purchase for '.strtoupper($params['exam_type'] ?? 'exam'),
            'streaming' => 'Streaming subscription for '.($params['platform'] ?? 'streaming'),
            default => ucfirst($category).' purchase',
        };
    }

    protected function successMessage(string $category): string
    {
        return match ($category) {
            'airtime' => 'Airtime purchased successfully',
            'data' => 'Data purchased successfully',
            'electricity' => 'Electricity purchased successfully',
            'cable' => 'Cable subscription successful',
            'education' => 'Exam pin purchased successfully',
            'streaming' => 'Streaming subscription successful',
            default => ucfirst($category).' purchase successful',
        };
    }

    protected function successData(string $category, array $params, array $response, string $provider): array
    {
        $base = [
            'status' => 'successful',
            'provider' => $provider,
            'provider_reference' => $this->providerReference($response),
        ];

        return match ($category) {
            'airtime' => $base + [
                'phone_number' => $params['phone_number'],
                'network' => $params['network'],
            ],
            'data' => $base + [
                'phone_number' => $params['phone_number'],
                'network' => $params['network'],
                'plan' => $params['plan'],
            ],
            'electricity' => $base + [
                'meter_number' => $params['meter_number'],
                'token' => $response['content']['transactions']['token'] ?? null,
                'units' => $response['content']['transactions']['units'] ?? null,
            ],
            'cable' => $base + [
                'smartcard_number' => $params['smartcard_number'],
                'cable' => $params['cable'],
                'package' => $params['package'],
            ],
            'education' => $base + [
                'exam_type' => $params['exam_type'],
                'quantity' => $params['quantity'] ?? 1,
                'pin' => $response['content']['transactions']['pin'] ?? $response['content']['pin'] ?? null,
            ],
            'streaming' => $base + [
                'platform' => $params['platform'],
                'plan' => $params['plan'],
                'recipient' => $params['recipient'] ?? $params['phone_number'] ?? null,
            ],
            default => $base,
        };
    }

    protected function processingData(Transaction $transaction): array
    {
        return [
            'status' => 'processing',
            'reference' => $transaction->reference,
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
        ];
    }

    protected function responseFromExisting(Transaction $transaction): array
    {
        if ($transaction->status === 'successful') {
            return [
                'status' => 'successful',
                'success' => true,
                'message' => $this->successMessage($transaction->category),
                'transaction' => $transaction,
                'data' => array_merge($transaction->metadata ?? [], ['status' => 'successful']),
            ];
        }

        if ($transaction->status === 'pending') {
            return [
                'status' => 'processing',
                'success' => true,
                'message' => 'Transaction already submitted. Confirming delivery...',
                'transaction' => $transaction,
                'data' => $this->processingData($transaction),
            ];
        }

        return [
            'status' => 'failed',
            'success' => false,
            'message' => $transaction->last_error ?? 'Transaction failed',
            'transaction' => $transaction,
            'data' => ['reference' => $transaction->reference],
        ];
    }

    protected function providerReference(array $response): ?string
    {
        return $response['content']['transactions']['transactionId']
            ?? $response['content']['transaction_hash']
            ?? $response['content']['transaction']['id']
            ?? $response['content']['ref']
            ?? $response['content']['id']
            ?? null;
    }

    protected function mergeMetadata(Transaction $transaction, array $values): array
    {
        return array_merge((array) $transaction->metadata, $values);
    }
}
