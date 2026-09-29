<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Services\ProviderRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = min(max((int) $request->get('limit', 10), 1), 100);

        $query = User::query();

        $term = trim((string) $request->input('search'));

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        $items = collect($users->items())->map(fn (User $user) => $this->userPayload($user));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $users->total(),
                'page' => $users->currentPage(),
                'limit' => $users->perPage(),
                'totalPages' => $users->lastPage(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = min(max((int) $request->get('limit', 10), 1), 100);

        $query = Transaction::with('user');

        if ($request->has('type')) {
            $query->where('category', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $term = trim((string) $request->input('search'));

        if ($term !== '') {
            $query->where('reference', 'like', "%{$term}%");
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $items = collect($transactions->items())
            ->map(fn (Transaction $txn) => $this->transactionPayload($txn));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $transactions->total(),
                'page' => $transactions->currentPage(),
                'limit' => $transactions->perPage(),
                'totalPages' => $transactions->lastPage(),
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $totalTransactions = Transaction::count();
        $successful = Transaction::where('status', 'successful')->count();
        $totalRevenue = (float) Transaction::where('type', 'credit')
            ->where('status', 'successful')
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'totalUsers' => User::count(),
                'totalTransactions' => $totalTransactions,
                'totalRevenue' => $totalRevenue,
                'successRate' => $totalTransactions > 0 ? round(($successful / $totalTransactions) * 100, 1) : 0.0,
                'activeUsers' => User::where('is_active', true)->count(),
                'pendingTransactions' => Transaction::where('status', 'pending')->count(),
                'failedTransactions' => Transaction::where('status', 'failed')->count(),
                'todayTransactions' => Transaction::whereDate('created_at', today())->count(),
                'todayRevenue' => (float) Transaction::where('type', 'credit')
                    ->where('status', 'successful')
                    ->whereDate('created_at', today())
                    ->sum('amount'),
            ],
        ]);
    }

    public function aggregatorHealth(): JsonResponse
    {
        $report = app(ProviderRouter::class)->healthyProvidersReport();

        $unhealthy = collect($report)->filter(fn (array $status) => ! $status['healthy'])->keys()->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $report,
                'unhealthy' => $unhealthy,
                'checkedAt' => now()->toISO8601String(),
            ],
        ]);
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => strtolower((string) $user->role),
            'isVerified' => (bool) $user->email_verified_at,
            'isActive' => (bool) $user->is_active,
            'createdAt' => $user->created_at?->toISO8601String(),
        ];
    }

    protected function transactionPayload(Transaction $txn): array
    {
        return [
            'id' => $txn->id,
            'type' => $txn->category,
            'category' => $txn->category,
            'reference' => $txn->reference,
            'description' => $txn->description,
            'totalAmount' => (float) $txn->amount,
            'amount' => (float) $txn->amount,
            'fee' => (float) ($txn->fee ?? 0),
            'status' => $txn->status,
            'provider' => $txn->provider,
            'paymentGateway' => $txn->provider,
            'providerReference' => $txn->provider_reference,
            'createdAt' => $txn->created_at?->toISO8601String(),
            'completedAt' => $txn->completed_at?->toISO8601String(),
            'user' => $txn->user ? [
                'id' => $txn->user->id,
                'firstName' => $txn->user->first_name,
                'lastName' => $txn->user->last_name,
                'email' => $txn->user->email,
            ] : null,
        ];
    }
}
