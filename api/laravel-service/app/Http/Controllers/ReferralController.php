<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
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
            'data' => [
                'code' => $user->referral_code,
                'link' => rtrim((string) config('app.url'), '/').'/signup?ref='.$user->referral_code,
                'totalEarned' => (float) Transaction::where('user_id', $user->id)
                    ->where('category', 'referral')
                    ->where('type', 'credit')
                    ->where('status', 'successful')
                    ->sum('amount'),
                'referralsCount' => $user->referrals()->count(),
            ],
        ]);
    }
}
