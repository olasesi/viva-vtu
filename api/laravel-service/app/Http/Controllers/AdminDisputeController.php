<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminDisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dispute::with(['user', 'transaction'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $disputes = $query->paginate((int) $request->get('limit', 20));

        return response()->json(['success' => true, 'data' => $disputes]);
    }

    public function show(int $id): JsonResponse
    {
        $dispute = Dispute::with(['user', 'transaction', 'resolver'])->find($id);

        if (! $dispute) {
            return response()->json(['success' => false, 'message' => 'Dispute not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $dispute]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dispute = Dispute::find($id);

        if (! $dispute) {
            return response()->json(['success' => false, 'message' => 'Dispute not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,under_review,resolved,rejected',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $adminId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $dispute->update([
            'status' => $request->input('status'),
            'admin_note' => $request->input('admin_note'),
            'resolved_by' => in_array($request->input('status'), ['resolved', 'rejected'], true) ? $adminId : $dispute->resolved_by,
            'resolved_at' => in_array($request->input('status'), ['resolved', 'rejected'], true) ? now() : $dispute->resolved_at,
        ]);

        return response()->json(['success' => true, 'message' => 'Dispute updated', 'data' => $dispute->fresh()->load(['user', 'transaction'])]);
    }
}
