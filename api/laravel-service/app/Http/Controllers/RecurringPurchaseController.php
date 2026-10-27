<?php

namespace App\Http\Controllers;

use App\Models\RecurringPurchase;
use App\Services\RecurringPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class RecurringPurchaseController extends Controller
{
    public function __construct(protected RecurringPurchaseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        if (! $userId) {
            return $this->unauthorized();
        }

        $query = RecurringPurchase::where('user_id', $userId)->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        if (! $userId) {
            return $this->unauthorized();
        }

        $validator = Validator::make($request->all(), array_merge([
            'name' => 'required|string|max:120',
            'category' => 'required|in:airtime,data,electricity,cable,education,streaming',
            'frequency' => 'required|in:daily,weekly,monthly',
            'interval' => 'nullable|integer|min:1|max:12',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'start_at' => 'nullable|date|after:now',
        ], $this->payloadRules((string) $request->input('category'))));

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $purchase = $this->service->create($userId, [
            'name' => $validated['name'],
            'category' => $validated['category'],
            'payload' => $validated['payload'],
            'frequency' => $validated['frequency'],
            'interval' => $validated['interval'] ?? 1,
            'day_of_month' => $validated['day_of_month'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring purchase scheduled',
            'data' => $purchase,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $purchase = $this->findOwned($request, $id);

        if (! $purchase) {
            return $this->notFound();
        }

        return response()->json(['success' => true, 'data' => $purchase]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $purchase = $this->findOwned($request, $id);

        if (! $purchase) {
            return $this->notFound();
        }

        $validator = Validator::make($request->all(), array_merge([
            'name' => 'sometimes|required|string|max:120',
            'frequency' => 'sometimes|required|in:daily,weekly,monthly',
            'interval' => 'nullable|integer|min:1|max:12',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'start_at' => 'nullable|date|after:now',
        ], $this->payloadRules($purchase->category, true)));

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $scheduleChanged = $request->exists('frequency')
            || $request->exists('interval')
            || $request->exists('day_of_month')
            || $request->exists('start_at');

        if (array_key_exists('name', $validated)) {
            $purchase->name = $validated['name'];
        }

        if (array_key_exists('frequency', $validated)) {
            $purchase->frequency = $validated['frequency'];
        }

        if ($request->exists('interval')) {
            $purchase->interval = (int) ($validated['interval'] ?? 1);
        }

        if ($request->exists('day_of_month')) {
            $purchase->day_of_month = $validated['day_of_month'] ?? null;
        }

        if (! empty($validated['payload'])) {
            $purchase->payload = array_merge($purchase->payload ?? [], $validated['payload']);
            $purchase->amount = $purchase->payload['amount'];
        }

        if ($scheduleChanged) {
            $purchase->next_run_at = ! empty($validated['start_at'])
                ? Carbon::parse($validated['start_at'])
                : $this->service->computeNextRunAt(
                    now(),
                    $purchase->frequency,
                    $purchase->interval,
                    $purchase->day_of_month,
                );
        }

        $purchase->save();

        return response()->json([
            'success' => true,
            'message' => 'Recurring purchase updated',
            'data' => $purchase->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $purchase = $this->findOwned($request, $id);

        if (! $purchase) {
            return $this->notFound();
        }

        $purchase->update(['status' => 'cancelled', 'next_run_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring purchase cancelled',
            'data' => $purchase->fresh(),
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($request, $id, 'paused');
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $purchase = $this->findOwned($request, $id);

        if (! $purchase) {
            return $this->notFound();
        }

        if ($purchase->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'A cancelled recurring purchase cannot be resumed',
            ], 422);
        }

        $purchase->update([
            'status' => 'active',
            'failure_count' => 0,
            'next_run_at' => $this->service->computeNextRunAt(
                now(),
                $purchase->frequency,
                $purchase->interval,
                $purchase->day_of_month,
            ),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring purchase resumed',
            'data' => $purchase->fresh(),
        ]);
    }

    public function runNow(Request $request, int $id): JsonResponse
    {
        $purchase = $this->findOwned($request, $id);

        if (! $purchase) {
            return $this->notFound();
        }

        if ($purchase->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'A cancelled recurring purchase cannot be run',
            ], 422);
        }

        $outcome = $this->service->process($purchase, true);

        $successful = in_array($outcome['status'], ['successful', 'processing'], true);

        return response()->json([
            'success' => $successful,
            'message' => $outcome['message'],
            'data' => $purchase->fresh(),
        ], $successful ? 200 : 400);
    }

    protected function setStatus(Request $request, int $id, string $status): JsonResponse
    {
        $purchase = $this->findOwned($request, $id);

        if (! $purchase) {
            return $this->notFound();
        }

        if ($purchase->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'A cancelled recurring purchase cannot be changed',
            ], 422);
        }

        $purchase->update([
            'status' => $status,
            'next_run_at' => $status === 'paused' ? null : $purchase->next_run_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => $status === 'paused' ? 'Recurring purchase paused' : 'Recurring purchase updated',
            'data' => $purchase->fresh(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(string $category, bool $sometimes = false): array
    {
        $prefix = $sometimes ? 'sometimes|' : '';
        $amount = $prefix.'required|numeric|min:50';

        $rules = match ($category) {
            'airtime' => [
                'phone_number' => $prefix.'required|string',
                'amount' => $amount,
                'network' => $prefix.'required|string',
            ],
            'data' => [
                'phone_number' => $prefix.'required|string',
                'amount' => $amount,
                'network' => $prefix.'required|string',
                'plan' => $prefix.'required|string',
            ],
            'electricity' => [
                'amount' => $prefix.'required|numeric|min:500',
                'meter_number' => $prefix.'required|string',
                'meter_type' => $prefix.'required|in:prepaid,postpaid',
                'disco' => $prefix.'required|string',
            ],
            'cable' => [
                'amount' => $prefix.'required|numeric|min:500',
                'smartcard_number' => $prefix.'required|string',
                'cable' => $prefix.'required|string',
                'package' => $prefix.'required|string',
            ],
            'education' => [
                'exam_type' => $prefix.'required|string',
                'amount' => $prefix.'required|numeric|min:100',
                'recipient' => 'nullable|string',
                'quantity' => 'nullable|integer|min:1|max:10',
            ],
            'streaming' => [
                'platform' => $prefix.'required|string',
                'plan' => $prefix.'required|string',
                'amount' => $prefix.'required|numeric|min:100',
                'recipient' => 'nullable|string',
            ],
            default => [],
        };

        return collect($rules)
            ->mapWithKeys(fn ($rule, $key) => ["payload.{$key}" => $rule])
            ->all();
    }

    protected function findOwned(Request $request, int $id): ?RecurringPurchase
    {
        $userId = $this->userId($request);

        if (! $userId) {
            return null;
        }

        return RecurringPurchase::where('user_id', $userId)->find($id);
    }

    protected function userId(Request $request): ?string
    {
        return $request->input('auth_user.id')
            ?? $request->user()['id']
            ?? $request->user('api')['id']
            ?? null;
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Recurring purchase not found',
        ], 404);
    }

    protected function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}
