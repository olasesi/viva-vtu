<?php

namespace App\Http\Controllers;

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

        if (!$userId) {
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

        if (!$userId) {
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

        if (!isset($result['status']) || $result['status'] !== true) {
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

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $transactions = \App\Models\Transaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
