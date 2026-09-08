<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    public function submitAirtimeToCash(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'network' => 'required|string',
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:50',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
        ]);

        $serviceRequest = ServiceRequest::create([
            'user_id' => $userId,
            'type' => 'airtime_to_cash',
            'reference' => 'A2C-'.strtoupper(Str::random(12)),
            'status' => 'pending',
            'amount' => $validated['amount'],
            'payload' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Airtime-to-cash request submitted. Our team will review and pay out shortly.',
            'data' => $this->present($serviceRequest),
        ], 201);
    }

    public function submitBulk(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', Rule::in(['airtime', 'data'])],
            'items.*.phone_number' => ['required', 'string'],
            'items.*.network' => ['required', 'string'],
            'items.*.amount' => ['required', 'numeric', 'min:50'],
            'items.*.plan' => ['required_if:items.*.type,data', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $items = $validator->validated()['items'];
        $total = array_sum(array_map(fn ($item) => (float) $item['amount'], $items));

        $serviceRequest = ServiceRequest::create([
            'user_id' => $userId,
            'type' => 'bulk',
            'reference' => 'BULK-'.strtoupper(Str::random(12)),
            'status' => 'pending',
            'amount' => $total,
            'payload' => ['items' => $items],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bulk purchase request submitted for '.count($items).' recipient(s).',
            'data' => $this->present($serviceRequest),
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $requests = ServiceRequest::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $requests->through(fn (ServiceRequest $r) => $this->present($r)),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $serviceRequest = ServiceRequest::where('user_id', $userId)->find($id);

        if (! $serviceRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->present($serviceRequest)]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $userId = $this->userId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $serviceRequest = ServiceRequest::where('user_id', $userId)->find($id);

        if (! $serviceRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        if ($serviceRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending requests can be cancelled'], 400);
        }

        $serviceRequest->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Request cancelled', 'data' => $this->present($serviceRequest)]);
    }

    protected function present(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => $serviceRequest->id,
            'reference' => $serviceRequest->reference,
            'type' => $serviceRequest->type,
            'status' => $serviceRequest->status,
            'amount' => (float) $serviceRequest->amount,
            'payload' => $serviceRequest->payload,
            'admin_note' => $serviceRequest->admin_note,
            'created_at' => $serviceRequest->created_at,
            'fulfilled_at' => $serviceRequest->fulfilled_at,
        ];
    }

    protected function userId(Request $request): ?int
    {
        return $request->user()['id'] ?? $request->user('api')['id'] ?? null;
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}
