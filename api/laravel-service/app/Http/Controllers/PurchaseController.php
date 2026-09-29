<?php

namespace App\Http\Controllers;

use App\Services\TransactionService;
use App\Support\Phones\NigerianPhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    public function buyAirtime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:50',
            'network' => 'required|string',
        ]);

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $result = $this->transactionService->execute('airtime', $userId, $validated);

        return $this->mapResult($result);
    }

    public function buyData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:50',
            'network' => 'required|string',
            'plan' => 'required|string',
        ]);

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $result = $this->transactionService->execute('data', $userId, $validated);

        return $this->mapResult($result);
    }

    public function buyElectricity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:500',
            'meter_number' => 'required|string',
            'meter_type' => 'required|string|in:prepaid,postpaid',
            'disco' => 'required|string',
        ]);

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $result = $this->transactionService->execute('electricity', $userId, $validated);

        return $this->mapResult($result);
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

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        if ($validated['action'] === 'validate') {
            $verification = $this->transactionService->verifyCustomer('cable', [
                'serviceID' => $validated['cable'],
                'billersCode' => $validated['smartcard_number'],
            ]);

            return response()->json($verification, $verification['success'] ? 200 : 400);
        }

        $result = $this->transactionService->execute('cable', $userId, $validated);

        return $this->mapResult($result);
    }

    public function buyExam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => 'required|string',
            'amount' => 'required|numeric|min:100',
            'recipient' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $result = $this->transactionService->execute('education', $userId, $validated);

        return $this->mapResult($result);
    }

    public function buyStreaming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string',
            'plan' => 'required|string',
            'amount' => 'required|numeric|min:100',
            'recipient' => 'nullable|string',
        ]);

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $result = $this->transactionService->execute('streaming', $userId, $validated);

        return $this->mapResult($result);
    }

    public function verifyPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'network' => 'nullable|string|in:mtn,glo,9mobile,airtel',
        ]);

        $check = NigerianPhoneNumber::validateForNetwork(
            $validated['phone_number'],
            $validated['network'] ?? ''
        );

        $data = [
            'phone_number' => $validated['phone_number'],
            'normalized' => $check['normalized'],
            'valid' => $check['valid'],
            'network' => $check['network'],
            'declared_network' => $check['declared_network'],
            'network_match' => $check['network_match'],
        ];

        if (! $check['valid']) {
            return response()->json([
                'success' => false,
                'message' => $check['reason'],
                'data' => $data,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Validation successful',
            'data' => $data,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|in:cable,electricity',
            'serviceID' => 'required|string',
            'billersCode' => 'required|string',
        ]);

        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $verification = $this->transactionService->verifyCustomer($validated['category'], [
            'serviceID' => $validated['serviceID'],
            'billersCode' => $validated['billersCode'],
        ]);

        return response()->json($verification, $verification['success'] ? 200 : 400);
    }

    protected function mapResult(array $result): JsonResponse
    {
        $transaction = $result['transaction'] ?? null;
        $data = $result['data'] ?? [];

        if ($result['status'] === 'successful' && $transaction) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => array_merge([
                    'reference' => $transaction->reference,
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                ], $data),
            ]);
        }

        if ($result['status'] === 'processing' && $transaction) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => array_merge([
                    'reference' => $transaction->reference,
                    'transaction_id' => $transaction->id,
                ], $data),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Purchase failed',
            'data' => $data ?: null,
        ], 400);
    }

    protected function userId(Request $request): ?int
    {
        return $request->user()['id'] ?? $request->user('api')['id'] ?? null;
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}
