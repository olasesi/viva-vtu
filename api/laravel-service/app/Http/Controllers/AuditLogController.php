<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()->with('causer')->orderByDesc('created_at');

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->input('causer_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->get('limit', 25)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $activity = Activity::with('causer')->find($id);

        if (! $activity) {
            return response()->json(['success' => false, 'message' => 'Audit log not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $activity]);
    }
}
