<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\PaystackService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected WalletService $walletService;

    protected PaystackService $paystackService;

    public function __construct(WalletService $walletService, PaystackService $paystackService)
    {
        $this->walletService = $walletService;
        $this->paystackService = $paystackService;
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
            'email' => 'required|email',
        ]);

        $amount = $validated['amount'];
        $email = $validated['email'];
        $metadata = [
            'user_id' => $userId,
            'type' => 'wallet_fund',
        ];

        $result = $this->paystackService->initializeTransaction($amount, $email, $metadata);

        if (! isset($result['status']) || $result['status'] !== true) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initialize payment',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized',
            'data' => [
                'authorization_url' => $result['data']['authorization_url'] ?? null,
                'access_code' => $result['data']['access_code'] ?? null,
                'reference' => $result['data']['reference'] ?? null,
            ],
        ]);
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

        $credited = $this->walletService->credit(
            $userId,
            $amount,
            $reference,
            'Wallet funding via Paystack'
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
