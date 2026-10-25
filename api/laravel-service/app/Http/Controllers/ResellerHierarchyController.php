<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResellerHierarchyController extends Controller
{
    public function tree(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $downline = $user->downline()->select(['id', 'first_name', 'last_name', 'email', 'is_reseller', 'reseller_margin_rate', 'created_at'])->get()->map(fn ($m) => [
            'id' => $m->id,
            'name' => trim($m->first_name.' '.$m->last_name),
            'email' => $m->email,
            'isReseller' => (bool) $m->is_reseller,
            'marginRate' => (float) $m->reseller_margin_rate,
            'joinedAt' => $m->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'parent' => $user->resellerParent ? [
                    'id' => $user->resellerParent->id,
                    'name' => trim($user->resellerParent->first_name.' '.$user->resellerParent->last_name),
                ] : null,
                'downline' => $downline,
                'downlineCount' => $downline->count(),
            ],
        ]);
    }

    public function assign(Request $request): JsonResponse
    {
        $actorId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $validator = Validator::make($request->all(), [
            'reseller_id' => 'required|integer|exists:users,id',
            'parent_id' => 'required|integer|exists:users,id',
            'margin_rate' => 'nullable|numeric|min:0|max:0.5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $actor = $actorId ? User::find($actorId) : null;

        if (! $actor || strtolower((string) $actor->role) !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reseller = User::find((int) $request->input('reseller_id'));
        $parent = User::find((int) $request->input('parent_id'));

        $reseller->update([
            'reseller_parent_id' => $parent->id,
            'is_reseller' => true,
            'reseller_margin_rate' => (float) ($request->input('margin_rate') ?? $reseller->reseller_margin_rate ?? 0.01),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reseller hierarchy updated',
            'data' => [
                'resellerId' => $reseller->id,
                'parentId' => $parent->id,
                'marginRate' => (float) $reseller->reseller_margin_rate,
            ],
        ]);
    }
}
