<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Services\FlutterwaveService;
use App\Services\PaystackService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    protected WalletService $walletService;

    protected PaystackService $paystackService;

    protected FlutterwaveService $flutterwaveService;

    public function __construct(WalletService $walletService, PaystackService $paystackService, FlutterwaveService $flutterwaveService)
    {
        $this->walletService = $walletService;
        $this->paystackService = $paystackService;
        $this->flutterwaveService = $flutterwaveService;
    }

    public function getBalance(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $balance = $this->walletService->getBalance($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balance,
                'currency' => 'NGN',
            ],
        ]);
    }

    public function fund(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:500000',
            'email' => 'nullable|email',
            'payment_method' => 'nullable|in:paystack,flutterwave',
            'paymentMethod' => 'nullable|in:paystack,flutterwave',
        ]);

        $amount = (float) $validated['amount'];
        $method = $validated['payment_method'] ?? $validated['paymentMethod'] ?? 'paystack';

        $email = $validated['email'] ?? $request->input('auth_user.email');

        if (! $email) {
            $email = User::where('id', $userId)->value('email');
        }

        if (! $email) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required to initialize payment',
            ], 422);
        }

        $metadata = [
            'user_id' => $userId,
            'type' => 'wallet_fund',
        ];

        if ($method === 'flutterwave') {
            return $this->initializeFlutterwaveFunding($userId, $amount, $email, $metadata);
        }

        return $this->initializePaystackFunding($userId, $amount, $email, $metadata);
    }

    protected function initializePaystackFunding(int $userId, float $amount, string $email, array $metadata): JsonResponse
    {
        $result = $this->paystackService->initializeTransaction($amount, $email, $metadata);

        if (! isset($result['status']) || $result['status'] !== true) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initialize payment',
            ], 400);
        }

        $reference = $result['data']['reference'] ?? null;

        if ($reference) {
            $this->recordPendingFunding($userId, $amount, $reference, 'paystack', 'Wallet funding via Paystack');
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized',
            'data' => [
                'authorization_url' => $result['data']['authorization_url'] ?? null,
                'access_code' => $result['data']['access_code'] ?? null,
                'reference' => $reference,
                'payment_method' => 'paystack',
            ],
        ]);
    }

    protected function initializeFlutterwaveFunding(int $userId, float $amount, string $email, array $metadata): JsonResponse
    {
        $txRef = 'VIVATU-FLW-'.strtoupper(Str::random(16));

        $result = $this->flutterwaveService->initializePayment($amount, 'NGN', $email, $txRef, $metadata);

        if (! isset($result['status']) || $result['status'] !== 'success') {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initialize payment',
            ], 400);
        }

        $reference = $result['data']['tx_ref'] ?? $txRef;

        $this->recordPendingFunding($userId, $amount, $reference, 'flutterwave', 'Wallet funding via Flutterwave');

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized',
            'data' => [
                'authorization_url' => $result['data']['link'] ?? null,
                'reference' => $reference,
                'payment_method' => 'flutterwave',
            ],
        ]);
    }

    protected function recordPendingFunding(int $userId, float $amount, string $reference, string $provider, string $description): void
    {
        $wallet = $this->walletService->ensureWalletExists($userId);

        Transaction::firstOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'category' => 'wallet_fund',
                'description' => $description,
                'amount' => $amount,
                'status' => 'pending',
                'provider' => $provider,
            ]
        );
    }

    public function verify(Request $request, string $reference): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (! $reference) {
            return response()->json(['success' => false, 'message' => 'Reference required'], 422);
        }

        $transaction = Transaction::where('reference', $reference)->first();
        $provider = $transaction->provider ?? 'paystack';

        if ($provider === 'flutterwave') {
            return $this->verifyFlutterwavePayment($userId, $reference, $transaction);
        }

        return $this->verifyPaystackPayment($userId, $reference, $transaction);
    }

    protected function verifyPaystackPayment(int $userId, string $reference, ?Transaction $transaction): JsonResponse
    {
        $result = $this->paystackService->verifyTransaction($reference);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify transaction with payment provider',
            ], 502);
        }

        if (! isset($result['status']) || $result['status'] !== true) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Transaction verification failed',
            ], 400);
        }

        $data = $result['data'] ?? [];
        $paymentStatus = strtolower((string) ($data['status'] ?? ''));

        if ($paymentStatus !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Payment not successful',
                'data' => ['status' => $paymentStatus],
            ], 400);
        }

        $amount = isset($data['amount']) ? round(((float) $data['amount']) / 100, 2) : 0;
        $providerReference = $data['id'] ?? null;

        return $this->completeWalletCredit($userId, $reference, $amount, $providerReference, $transaction, 'Wallet funding via Paystack');
    }

    protected function verifyFlutterwavePayment(int $userId, string $reference, ?Transaction $transaction): JsonResponse
    {
        $result = $this->flutterwaveService->verifyByReference($reference);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify transaction with payment provider',
            ], 502);
        }

        if (! isset($result['status']) || $result['status'] !== 'success') {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Transaction verification failed',
            ], 400);
        }

        $data = $result['data'] ?? [];
        $paymentStatus = strtolower((string) ($data['status'] ?? ''));

        if ($paymentStatus !== 'successful') {
            return response()->json([
                'success' => false,
                'message' => 'Payment not successful',
                'data' => ['status' => $paymentStatus],
            ], 400);
        }

        $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : 0;
        $providerReference = $data['id'] ?? null;

        return $this->completeWalletCredit($userId, $reference, $amount, $providerReference, $transaction, 'Wallet funding via Flutterwave');
    }

    protected function completeWalletCredit(int $userId, string $reference, float $amount, $providerReference, ?Transaction $transaction, string $description): JsonResponse
    {
        $credited = $this->walletService->credit(
            $userId,
            $amount,
            $reference,
            $transaction?->description ?? $description
        );

        $transaction = Transaction::where('reference', $reference)->first();

        if (! $transaction || (! $credited && $transaction->status !== 'successful')) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verified but wallet credit failed',
            ], 500);
        }

        if ($providerReference && $transaction->provider_reference === null) {
            $transaction->update(['provider_reference' => $providerReference]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment verified',
            'data' => [
                'balance' => $this->walletService->getBalance($userId),
                'currency' => 'NGN',
                'transaction' => $transaction,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $transactions = Transaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
