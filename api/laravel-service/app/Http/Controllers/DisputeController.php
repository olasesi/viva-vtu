<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $disputes = Dispute::with('transaction')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->get('limit', 10));

        return response()->json(['success' => true, 'data' => $disputes]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|integer|exists:transactions,id',
            'reason' => 'required|in:not_received,debited_but_failed,wrong_amount,unauthorized,other',
            'description' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::where('id', $request->input('transaction_id'))
            ->where('user_id', $userId)
            ->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $existing = Dispute::where('transaction_id', $transaction->id)->whereIn('status', ['open', 'under_review'])->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'A dispute already exists for this transaction'], 422);
        }

        $dispute = Dispute::create([
            'user_id' => $userId,
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'reason' => $request->input('reason'),
            'description' => $request->input('description'),
            'status' => 'open',
        ]);

        return response()->json(['success' => true, 'message' => 'Dispute submitted', 'data' => $dispute->load('transaction')], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        $dispute = Dispute::with('transaction')->where('user_id', $userId)->find($id);

        if (! $dispute) {
            return response()->json(['success' => false, 'message' => 'Dispute not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $dispute]);
    }
}
