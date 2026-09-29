<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Transaction;
use App\Services\IdempotencyService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResellerController extends Controller
{
    protected const ALLOWED_CATEGORIES = ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'];

    public function issueKey(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $issued = ApiKey::issue((int) $request->input('user_id'), $request->input('name'));

        return response()->json([
            'success' => true,
            'message' => 'API key issued. Store it now; it will not be shown again.',
            'data' => $issued,
        ], 201);
    }

    public function revokeKey(Request $request, int $keyId): JsonResponse
    {
        $apiKey = ApiKey::find($keyId);

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found',
            ], 404);
        }

        $apiKey->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked',
        ]);
    }

    public function purchase(Request $request, string $category): JsonResponse
    {
        if (! in_array($category, self::ALLOWED_CATEGORIES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported purchase category',
            ], 422);
        }

        $user = $request->attributes->get('auth_reseller_user');

        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required',
            ], 422);
        }

        $key = Str::limit($key, 128);
        $idempotency = app(IdempotencyService::class);

        $stored = $idempotency->completedResponse($user->id, $key);

        if ($stored) {
            return $this->respond($stored, true);
        }

        if ($idempotency->claim($user->id, $key) === 'conflict') {
            return response()->json([
                'success' => false,
                'message' => 'A request with this Idempotency-Key is already in flight',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $params = $request->only([
            'amount',
            'phone_number',
            'network',
            'plan',
            'disco',
            'meter_number',
            'meter_type',
            'cable',
            'smartcard_number',
            'package',
            'exam_type',
            'quantity',
            'product_id',
            'biller_code',
        ]);

        $result = app(TransactionService::class)->execute($category, $user->id, $params);

        $reference = $result['transaction']['reference'] ?? $result['reference'] ?? null;

        $idempotency->resolve($user->id, $key, $reference, $result);

        return $this->respond($result, false);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_reseller_user');

        $reference = trim((string) $request->query('reference'));

        if ($reference === '') {
            return response()->json([
                'success' => false,
                'message' => 'reference query parameter is required',
            ], 422);
        }

        $transaction = Transaction::with('user')
            ->where('user_id', $user->id)
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        if ($request->query('requery') == 1 && $transaction->status === 'pending') {
            app(TransactionService::class)->requery($transaction);
            $transaction->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($transaction),
        ]);
    }

    protected function respond(array $result, bool $replayed): JsonResponse
    {
        $statusCode = $result['status'] === 'successful' ? 200 : ($result['status'] === 'validation_failed' || $result['status'] === 'failed' ? 422 : 202);

        return response()->json([
            'success' => $result['success'] ?? false,
            'status' => $result['status'] ?? 'unknown',
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
            'transaction' => $result['transaction'] ?? null,
            'meta' => [
                'idempotentReplayed' => $replayed,
            ],
        ], $statusCode);
    }

    protected function payload(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'category' => $transaction->category,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'amount' => (float) $transaction->amount,
            'fee' => (float) ($transaction->fee ?? 0),
            'provider' => $transaction->provider,
            'providerReference' => $transaction->provider_reference,
            'lastError' => $transaction->last_error,
            'completedAt' => $transaction->completed_at?->toISO8601String(),
            'createdAt' => $transaction->created_at?->toISO8601String(),
        ];
    }
}
