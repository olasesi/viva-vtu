<?php

namespace App\Http\Controllers;

use App\Models\PaymentLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\PaystackService;
use App\Services\FlutterwaveService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    protected PaystackService $paystackService;
    protected FlutterwaveService $flutterwaveService;
    protected WalletService $walletService;

    public function __construct(
        PaystackService $paystackService,
        FlutterwaveService $flutterwaveService,
        WalletService $walletService
    ) {
        $this->paystackService = $paystackService;
        $this->flutterwaveService = $flutterwaveService;
        $this->walletService = $walletService;
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

        if (!$verificationResponse) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if (!isset($payload['data'])) {
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

                if (!$existingTransaction) {
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

        if (!$isValid) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if (!isset($payload['data'])) {
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

                if (!$existingTransaction) {
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
