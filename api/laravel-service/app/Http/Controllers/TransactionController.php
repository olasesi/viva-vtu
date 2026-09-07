<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $transaction = $this->ownTransaction($request, $id);

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $transaction = $this->ownTransaction($request, $id);

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'provider' => $transaction->provider,
                'provider_reference' => $transaction->provider_reference,
                'amount' => $transaction->amount,
                'attempts' => $transaction->attempts,
                'last_error' => $transaction->last_error,
                'completed_at' => $transaction->completed_at,
                'reversed_at' => $transaction->reversed_at,
                'created_at' => $transaction->created_at,
            ],
        ]);
    }

    protected function ownTransaction(Request $request, string $id): ?Transaction
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        return Transaction::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $query = Transaction::where('user_id', $userId);

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
