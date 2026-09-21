<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->input('auth_user.role')
            ?? $request->get('auth_user')['role']
            ?? $request->user()['role']
            ?? null;

        if ($role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: administrator access required',
            ], 403);
        }

        return $next($request);
    }
}
