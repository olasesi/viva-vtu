<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\VtpassService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    protected VtpassService $vtpassService;
    protected WalletService $walletService;

    public function __construct(VtpassService $vtpassService, WalletService $walletService)
    {
        $this->vtpassService = $vtpassService;
        $this->walletService = $walletService;
    }

    public function buyAirtime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:50',
            'network' => 'required|string',
        ]);

        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reference = 'AIR-' . strtoupper(Str::random(12));
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet || $wallet->balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ], 400);
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'category' => 'airtime',
            'reference' => $reference,
            'description' => "Airtime purchase for {$validated['phone_number']}",
            'amount' => $validated['amount'],
            'status' => 'pending',
            'metadata' => $validated,
        ]);

        $debitResult = $this->walletService->debit(
            $userId,
            $validated['amount'],
            $reference,
            "Airtime purchase for {$validated['phone_number']}"
        );

        if (!$debitResult) {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'Failed to debit wallet',
            ], 400);
        }

        $vtpassResponse = $this->vtpassService->purchaseAirtime([
            'phone_number' => $validated['phone_number'],
            'amount' => $validated['amount'],
            'network' => $validated['network'],
            'request_id' => $reference,
        ]);

        if (isset($vtpassResponse['code']) && $vtpassResponse['code'] === '000') {
            $transaction->update([
                'status' => 'successful',
                'provider_reference' => $vtpassResponse['content']['transactions']['transactionId'] ?? null,
                'completed_at' => now(),
                'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Airtime purchased successfully',
                'data' => [
                    'reference' => $reference,
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['amount'],
                    'phone_number' => $validated['phone_number'],
                    'network' => $validated['network'],
                ],
            ]);
        }

        $this->walletService->credit(
            $userId,
            $validated['amount'],
            'REV-' . $reference,
            "Reversal for failed airtime purchase {$reference}"
        );

        $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
        ]);

        return response()->json([
            'success' => false,
            'message' => $vtpassResponse['response_message'] ?? 'Airtime purchase failed',
        ], 400);
    }

    public function buyData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:50',
            'network' => 'required|string',
            'plan' => 'required|string',
        ]);

        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reference = 'DAT-' . strtoupper(Str::random(12));
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet || $wallet->balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ], 400);
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'category' => 'data',
            'reference' => $reference,
            'description' => "Data purchase for {$validated['phone_number']}",
            'amount' => $validated['amount'],
            'status' => 'pending',
            'metadata' => $validated,
        ]);

        $debitResult = $this->walletService->debit(
            $userId,
            $validated['amount'],
            $reference,
            "Data purchase for {$validated['phone_number']}"
        );

        if (!$debitResult) {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'Failed to debit wallet',
            ], 400);
        }

        $vtpassResponse = $this->vtpassService->purchaseData([
            'phone_number' => $validated['phone_number'],
            'amount' => $validated['amount'],
            'network' => $validated['network'],
            'plan' => $validated['plan'],
            'request_id' => $reference,
        ]);

        if (isset($vtpassResponse['code']) && $vtpassResponse['code'] === '000') {
            $transaction->update([
                'status' => 'successful',
                'provider_reference' => $vtpassResponse['content']['transactions']['transactionId'] ?? null,
                'completed_at' => now(),
                'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data purchased successfully',
                'data' => [
                    'reference' => $reference,
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['amount'],
                    'phone_number' => $validated['phone_number'],
                    'network' => $validated['network'],
                    'plan' => $validated['plan'],
                ],
            ]);
        }

        $this->walletService->credit(
            $userId,
            $validated['amount'],
            'REV-' . $reference,
            "Reversal for failed data purchase {$reference}"
        );

        $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
        ]);

        return response()->json([
            'success' => false,
            'message' => $vtpassResponse['response_message'] ?? 'Data purchase failed',
        ], 400);
    }

    public function buyElectricity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:500',
            'meter_number' => 'required|string',
            'meter_type' => 'required|string|in:prepaid,postpaid',
            'disco' => 'required|string',
        ]);

        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reference = 'ELEC-' . strtoupper(Str::random(12));
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet || $wallet->balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ], 400);
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'category' => 'electricity',
            'reference' => $reference,
            'description' => "Electricity purchase for meter {$validated['meter_number']}",
            'amount' => $validated['amount'],
            'status' => 'pending',
            'metadata' => $validated,
        ]);

        $debitResult = $this->walletService->debit(
            $userId,
            $validated['amount'],
            $reference,
            "Electricity purchase for meter {$validated['meter_number']}"
        );

        if (!$debitResult) {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'Failed to debit wallet',
            ], 400);
        }

        $vtpassResponse = $this->vtpassService->purchaseElectricity([
            'amount' => $validated['amount'],
            'meter_number' => $validated['meter_number'],
            'meter_type' => $validated['meter_type'],
            'disco' => $validated['disco'],
            'request_id' => $reference,
        ]);

        if (isset($vtpassResponse['code']) && $vtpassResponse['code'] === '000') {
            $transaction->update([
                'status' => 'successful',
                'provider_reference' => $vtpassResponse['content']['transactions']['transactionId'] ?? null,
                'completed_at' => now(),
                'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Electricity purchased successfully',
                'data' => [
                    'reference' => $reference,
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['amount'],
                    'meter_number' => $validated['meter_number'],
                    'token' => $vtpassResponse['content']['transactions']['token'] ?? null,
                    'units' => $vtpassResponse['content']['transactions']['units'] ?? null,
                ],
            ]);
        }

        $this->walletService->credit(
            $userId,
            $validated['amount'],
            'REV-' . $reference,
            "Reversal for failed electricity purchase {$reference}"
        );

        $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
        ]);

        return response()->json([
            'success' => false,
            'message' => $vtpassResponse['response_message'] ?? 'Electricity purchase failed',
        ], 400);
    }

    public function buyCable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:500',
            'smartcard_number' => 'required|string',
            'cable' => 'required|string',
            'package' => 'required|string',
            'action' => 'required|string|in:validate,subscribe',
        ]);

        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reference = 'CABLE-' . strtoupper(Str::random(12));
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet || $wallet->balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ], 400);
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'category' => 'cable',
            'reference' => $reference,
            'description' => "Cable subscription for {$validated['smartcard_number']}",
            'amount' => $validated['amount'],
            'status' => 'pending',
            'metadata' => $validated,
        ]);

        $debitResult = $this->walletService->debit(
            $userId,
            $validated['amount'],
            $reference,
            "Cable subscription for {$validated['smartcard_number']}"
        );

        if (!$debitResult) {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'Failed to debit wallet',
            ], 400);
        }

        $vtpassResponse = $this->vtpassService->purchaseCable([
            'amount' => $validated['amount'],
            'smartcard_number' => $validated['smartcard_number'],
            'cable' => $validated['cable'],
            'package' => $validated['package'],
            'action' => $validated['action'],
            'request_id' => $reference,
        ]);

        if (isset($vtpassResponse['code']) && $vtpassResponse['code'] === '000') {
            $transaction->update([
                'status' => 'successful',
                'provider_reference' => $vtpassResponse['content']['transactions']['transactionId'] ?? null,
                'completed_at' => now(),
                'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cable subscription successful',
                'data' => [
                    'reference' => $reference,
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['amount'],
                    'smartcard_number' => $validated['smartcard_number'],
                    'cable' => $validated['cable'],
                    'package' => $validated['package'],
                ],
            ]);
        }

        $this->walletService->credit(
            $userId,
            $validated['amount'],
            'REV-' . $reference,
            "Reversal for failed cable purchase {$reference}"
        );

        $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], ['vtpass_response' => $vtpassResponse]),
        ]);

        return response()->json([
            'success' => false,
            'message' => $vtpassResponse['response_message'] ?? 'Cable subscription failed',
        ], 400);
    }
}
