<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResellerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken() ?? $request->header('X-API-Key');

        if (! $raw) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        $apiKey = ApiKey::where('key', hash('sha256', $raw))->first();

        if (! $apiKey || ! $apiKey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or deactivated API key',
            ], 401);
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'API key has expired',
            ], 401);
        }

        $user = $apiKey->user;

        if (! $user || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'API key owner is inactive',
            ], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->attributes->set('auth_reseller_user', $user);
        $request->attributes->set('auth_reseller_key', $apiKey);

        return $next($request);
    }
}
