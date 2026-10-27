<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminAction
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'current_pin',
        'pin',
        'pin_confirmation',
        'transaction_pin',
        'token',
        'challengeToken',
        'secret',
        'api_key',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $userId = $request->input('auth_user.id')
            ?? $request->user()['id']
            ?? $request->user('api')['id']
            ?? null;

        $causer = $userId ? User::find($userId) : null;

        $activity = activity('admin')
            ->withProperties([
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => $this->sanitize($request->except(['auth_user'])),
            ])
            ->event(strtolower($request->method()));

        if ($causer) {
            $activity->causedBy($causer);
        }

        $activity->log('Admin action: '.$request->method().' /'.$request->path());

        return $response;
    }

    private function sanitize(array $payload): array
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
