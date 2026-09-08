<?php

namespace App\Http\Controllers;

use App\Models\PaymentLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\FlutterwaveService;
use App\Services\PaystackService;
use App\Services\TransactionService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected PaystackService $paystackService;

    protected FlutterwaveService $flutterwaveService;

    protected WalletService $walletService;

    protected TransactionService $transactionService;

    public function __construct(
        PaystackService $paystackService,
        FlutterwaveService $flutterwaveService,
        WalletService $walletService,
        TransactionService $transactionService
    ) {
        $this->paystackService = $paystackService;
        $this->flutterwaveService = $flutterwaveService;
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }

    public function handlePaystack(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Paystack-Signature');

        PaymentLog::create([
            'user_id' => null,
            'transaction_id' => null,
            'gateway' => 'paystack',
            'event_type' => $payload['event'] ?? 'unknown',
            'payload' => $payload,
            'response' => [],
            'ip_address' => $request->ip(),
        ]);

        $verificationResponse = $this->paystackService->verifyWebhookSignature($payload, $signature);

        if (! $verificationResponse) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if (! isset($payload['data'])) {
            return response()->json(['success' => true, 'message' => 'No data'], 200);
        }

        $event = $payload['event'] ?? '';
        $data = $payload['data'];
        $reference = $data['reference'] ?? null;

        if ($event === 'charge.success' && $reference) {
            $metadata = $data['metadata'] ?? [];
            $userId = $metadata['user_id'] ?? null;

            if ($userId) {
                $existingTransaction = Transaction::where('reference', $reference)->first();

                if (! $existingTransaction) {
                    $wallet = Wallet::where('user_id', $userId)->first();

                    if ($wallet) {
                        $transaction = Transaction::create([
                            'user_id' => $userId,
                            'wallet_id' => $wallet->id,
                            'type' => 'credit',
                            'category' => 'wallet_fund',
                            'reference' => $reference,
                            'description' => 'Wallet funding via Paystack',
                            'amount' => $data['amount'] / 100,
                            'status' => 'successful',
                            'provider_reference' => $data['id'] ?? null,
                            'metadata' => $metadata,
                            'completed_at' => now(),
                        ]);

                        $this->walletService->credit(
                            $userId,
                            $data['amount'] / 100,
                            $reference,
                            'Wallet funding via Paystack'
                        );

                        PaymentLog::where('gateway', 'paystack')
                            ->where('payload->reference', $reference)
                            ->update(['transaction_id' => $transaction->id]);
                    }
                } else {
                    $existingTransaction->update([
                        'status' => 'successful',
                        'provider_reference' => $data['id'] ?? $existingTransaction->provider_reference,
                    ]);

                    if ($existingTransaction->status !== 'successful') {
                        $this->walletService->credit(
                            $userId,
                            $existingTransaction->amount,
                            $reference,
                            'Wallet funding via Paystack (webhook update)'
                        );
                    }
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    /**
     * Ingest an upstream aggregator (AidaPay / EasyAccess / ...) status
     * webhook and reconcile the matching pending transaction.
     */
    public function handleAggregator(Request $request, string $slug): JsonResponse
    {
        $providerConfig = config('aggregators.providers.'.$slug);
        $webhook = $providerConfig['webhook'] ?? null;

        if (! $providerConfig || ! $webhook) {
            return response()->json(['success' => false, 'message' => 'Unknown aggregator webhook'], 404);
        }

        $payload = (array) json_decode($request->getContent(), true);

        PaymentLog::create([
            'user_id' => null,
            'transaction_id' => null,
            'gateway' => $slug,
            'event_type' => $payload['type'] ?? 'unknown',
            'payload' => $payload,
            'response' => [],
            'ip_address' => $request->ip(),
        ]);

        $secretKey = $webhook['secret_from'] ?? 'webhook_secret';
        $secret = (string) ($providerConfig[$secretKey] ?? '');
        $header = (string) ($webhook['signature_header'] ?? 'Signature');

        if ($secret !== '') {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            $received = (string) ($request->header($header) ?? '');

            if (! hash_equals($expected, $received)) {
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
            }
        } else {
            Log::warning('Aggregator webhook received without a signature secret', ['slug' => $slug]);
        }

        $status = (string) (data_get($payload, $webhook['status_path'] ?? 'status') ?? '');
        $reference = data_get($payload, $webhook['reference_path'] ?? 'reference');
        $hash = data_get($payload, $webhook['hash_path'] ?? 'transaction_hash');

        $this->reconcile(
            $slug,
            $status,
            is_string($reference) ? $reference : null,
            is_string($hash) ? $hash : null,
            $webhook
        );

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    protected function reconcile(string $slug, string $status, ?string $reference, ?string $hash, array $webhook): void
    {
        $transaction = $reference
            ? Transaction::where('reference', $reference)->first()
            : null;

        if (! $transaction && $hash) {
            $transaction = Transaction::where('provider_reference', $hash)
                ->orWhere('metadata->provider_reference', $hash)
                ->first();
        }

        if (! $transaction) {
            Log::warning('Aggregator webhook for unknown transaction', [
                'slug' => $slug,
                'reference' => $reference,
                'hash' => $hash,
            ]);

            return;
        }

        if ($transaction->status !== 'pending') {
            return;
        }

        $normalized = strtolower((string) $status);
        $successful = array_map('strtolower', (array) ($webhook['successful_statuses'] ?? ['Completed']));
        $failed = array_map('strtolower', (array) ($webhook['failed_statuses'] ?? ['Refund', 'Cancelled']));

        if (in_array($normalized, $successful, true)) {
            $transaction->update([
                'status' => 'successful',
                'provider_reference' => $hash ?? $transaction->provider_reference,
                'completed_at' => now(),
                'last_error' => null,
                'metadata' => array_merge((array) $transaction->metadata, [
                    'aggregator_webhook' => [
                        'slug' => $slug,
                        'status' => $status,
                        'received_at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            Log::info('Transaction reconciled via aggregator webhook', [
                'slug' => $slug,
                'reference' => $transaction->reference,
            ]);

            PaymentLog::where('gateway', $slug)
                ->latest('id')
                ->first()?->update(['transaction_id' => $transaction->id]);

            return;
        }

        if (in_array($normalized, $failed, true)) {
            $this->transactionService->reverse($transaction, 'Aggregator webhook reported '.$status);

            PaymentLog::where('gateway', $slug)
                ->latest('id')
                ->first()?->update(['transaction_id' => $transaction->id]);
        }

        // Processing / Pending and anything else: transaction stays pending,
        // the scheduled requery job keeps polling until the final status.
    }

    public function handleFlutterwave(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('Verif-Hash');

        PaymentLog::create([
            'user_id' => null,
            'transaction_id' => null,
            'gateway' => 'flutterwave',
            'event_type' => $payload['event'] ?? 'unknown',
            'payload' => $payload,
            'response' => [],
            'ip_address' => $request->ip(),
        ]);

        $isValid = $this->flutterwaveService->verifyWebhookSignature($payload, $signature);

        if (! $isValid) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if (! isset($payload['data'])) {
            return response()->json(['success' => true, 'message' => 'No data'], 200);
        }

        $event = $payload['event'] ?? '';
        $data = $payload['data'];
        $txRef = $data['tx_ref'] ?? null;

        if ($event === 'charge.completed' && $txRef) {
            $metadata = $data['meta'] ?? [];
            $userId = $metadata['user_id'] ?? null;

            if ($userId) {
                $existingTransaction = Transaction::where('reference', $txRef)->first();

                if (! $existingTransaction) {
                    $wallet = Wallet::where('user_id', $userId)->first();

                    if ($wallet) {
                        $transaction = Transaction::create([
                            'user_id' => $userId,
                            'wallet_id' => $wallet->id,
                            'type' => 'credit',
                            'category' => 'wallet_fund',
                            'reference' => $txRef,
                            'description' => 'Wallet funding via Flutterwave',
                            'amount' => $data['amount'] ?? 0,
                            'status' => 'successful',
                            'provider_reference' => $data['id'] ?? null,
                            'metadata' => $metadata,
                            'completed_at' => now(),
                        ]);

                        $this->walletService->credit(
                            $userId,
                            $data['amount'] ?? 0,
                            $txRef,
                            'Wallet funding via Flutterwave'
                        );

                        PaymentLog::where('gateway', 'flutterwave')
                            ->where('payload->data->tx_ref', $txRef)
                            ->update(['transaction_id' => $transaction->id]);
                    }
                } else {
                    if ($existingTransaction->status !== 'successful') {
                        $existingTransaction->update([
                            'status' => 'successful',
                            'provider_reference' => $data['id'] ?? $existingTransaction->provider_reference,
                        ]);

                        $this->walletService->credit(
                            $userId,
                            $existingTransaction->amount,
                            $txRef,
                            'Wallet funding via Flutterwave (webhook update)'
                        );
                    }
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }
}
