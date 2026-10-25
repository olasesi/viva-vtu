<?php

namespace App\Http\Controllers;

use App\Models\ResellerWebhook;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResellerWebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $webhooks = ResellerWebhook::where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'data' => $webhooks->map(fn ($w) => [
                'id' => $w->id,
                'url' => $w->url,
                'isActive' => $w->is_active,
                'events' => $w->events ?? ['order.completed', 'order.failed'],
                'lastSentAt' => $w->last_sent_at?->toIso8601String(),
                'createdAt' => $w->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:500',
            'events' => 'nullable|array',
            'events.*' => 'in:order.completed,order.failed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $webhook = ResellerWebhook::create([
            'user_id' => $user->id,
            'url' => $request->input('url'),
            'secret' => Str::random(40),
            'is_active' => true,
            'events' => $request->input('events'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook registered',
            'data' => [
                'id' => $webhook->id,
                'url' => $webhook->url,
                'secret' => $webhook->secret,
                'events' => $webhook->events ?? ['order.completed', 'order.failed'],
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $webhook = ResellerWebhook::where('user_id', $user->id)->find($id);

        if (! $webhook) {
            return response()->json(['success' => false, 'message' => 'Webhook not found'], 404);
        }

        $webhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webhook removed',
        ]);
    }
}
