<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KycController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($user),
        ]);
    }

    public function submit(Request $request, KycService $kyc): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'bvn' => 'required_without:nin|nullable|digits:11',
            'nin' => 'required_without:bvn|nullable|digits:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $kyc->submit($user, (string) $request->input('bvn', ''), (string) $request->input('nin', ''));

        return response()->json([
            'success' => true,
            'message' => $user->kyc_level >= KycService::LEVEL_NIN
                ? 'KYC upgraded to verified level 2'
                : 'KYC details recorded',
            'data' => $this->payload($user),
        ], 200);
    }

    protected function payload(User $user): array
    {
        return [
            'level' => (int) $user->kyc_level,
            'verified' => $user->kyc_level >= KycService::LEVEL_BVN,
            'verifiedAt' => $user->kyc_verified_at?->toISO8601String(),
            'bvn' => $user->kyc_bvn_last4 ? substr(str_repeat('*', 7), 0, 7).$user->kyc_bvn_last4 : null,
            'nin' => $user->kyc_nin_last4 ? substr(str_repeat('*', 7), 0, 7).$user->kyc_nin_last4 : null,
        ];
    }
}
