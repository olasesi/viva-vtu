<?php

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyJwtToken
{
    protected Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => config('auth.service_url', 'http://auth:3001'),
            'timeout' => 5,
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token is required',
            ], 401);
        }

        $sharedSecret = config('jwt.shared_secret', '');
        $expectedHash = hash_hmac('sha256', $token, $sharedSecret);

        $providedHash = $request->header('X-Auth-Hash');

        if ($sharedSecret && $providedHash && hash_equals($expectedHash, $providedHash)) {
            $userId = $request->header('X-User-Id');
            $userEmail = $request->header('X-User-Email');
            $userRole = $request->header('X-User-Role', 'user');

            $request->merge([
                'auth_user' => [
                    'id' => $userId,
                    'email' => $userEmail,
                    'role' => $userRole,
                ],
            ]);

            return $next($request);
        }

        try {
            $response = $this->httpClient->post('/api/auth/verify', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['success']) && $body['success'] && isset($body['user'])) {
                $request->merge(['auth_user' => $body['user']]);
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication token',
            ], 401);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication service unavailable',
            ], 503);
        }
    }
}
