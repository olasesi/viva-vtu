<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TransactionPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionPinController extends Controller
{
    public function __construct(protected TransactionPinService $pinService) {}

    public function status(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $user->hasTransactionPin(),
                'set_at' => $user->transaction_pin_set_at,
            ],
        ]);
    }

    public function set(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($user->hasTransactionPin()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction PIN is already set. Use the change endpoint instead.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'pin' => 'required|digits:4',
            'pin_confirmation' => 'required|same:pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->pinService->set($user, (string) $request->input('pin'));

        return response()->json([
            'success' => true,
            'message' => 'Transaction PIN set successfully',
        ]);
    }

    public function change(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (! $user->hasTransactionPin()) {
            return response()->json(['success' => false, 'message' => 'No transaction PIN set'], 400);
        }

        $validator = Validator::make($request->all(), [
            'current_pin' => 'required|digits:4',
            'pin' => 'required|digits:4',
            'pin_confirmation' => 'required|same:pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->pinService->verify($user, (string) $request->input('current_pin'));

        if ($result['status'] !== 'valid') {
            return response()->json([
                'success' => false,
                'message' => $this->messageFor($result),
            ], 422);
        }

        $this->pinService->set($user, (string) $request->input('pin'));

        return response()->json([
            'success' => true,
            'message' => 'Transaction PIN updated successfully',
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'pin' => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $this->pinService->clear($user, (string) $request->input('pin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction PIN',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction PIN removed',
        ]);
    }

    protected function currentUser(Request $request): ?User
    {
        $id = $request->input('auth_user.id')
            ?? $request->user()['id']
            ?? $request->user('api')['id']
            ?? null;

        return $id ? User::find($id) : null;
    }

    protected function messageFor(array $result): string
    {
        return match ($result['status']) {
            'locked' => 'Too many incorrect PIN attempts. Try again in 15 minutes.',
            'not_set' => 'No transaction PIN set',
            default => 'Invalid transaction PIN',
        };
    }
}
