<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TransactionPinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTransactionPin
{
    public function __construct(protected TransactionPinService $pinService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->input('auth_user.id')
            ?? $request->user()['id']
            ?? $request->user('api')['id']
            ?? null;

        if (! $userId) {
            return $next($request);
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasTransactionPin()) {
            return $next($request);
        }

        $threshold = (float) config('wallet.transaction_pin_threshold', 0);
        $amount = (float) $request->input('amount', 0);

        if ($threshold > 0 && $amount < $threshold) {
            return $next($request);
        }

        $pin = $request->input('transaction_pin');

        if (! $pin) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction PIN is required',
                'code' => 'transaction_pin_required',
            ], 422);
        }

        $result = $this->pinService->verify($user, (string) $pin);

        if ($result['status'] === 'valid') {
            return $next($request);
        }

        $message = match ($result['status']) {
            'locked' => 'Too many incorrect PIN attempts. Try again in 15 minutes.',
            'invalid' => 'Invalid transaction PIN',
            default => 'Transaction PIN verification failed',
        };

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'transaction_pin_invalid',
        ], 422);
    }
}
