<?php

namespace App\Services;

use App\Models\IdempotentRequest;
use Illuminate\Database\QueryException;

class IdempotencyService
{
    public function completedResponse(int $userId, string $key): ?array
    {
        $request = IdempotentRequest::where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();

        if (! $request || $request->status !== 'completed' || ! $request->response) {
            return null;
        }

        $decoded = json_decode($request->response, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function claim(int $userId, string $key): string|IdempotentRequest
    {
        try {
            return IdempotentRequest::create([
                'user_id' => $userId,
                'idempotency_key' => $key,
                'status' => 'pending',
            ]);
        } catch (QueryException) {
            return 'conflict';
        }
    }

    public function resolve(int $userId, string $key, ?string $reference, array $response): void
    {
        IdempotentRequest::where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->update([
                'status' => 'completed',
                'reference' => $reference,
                'response' => json_encode($response),
            ]);
    }
}
