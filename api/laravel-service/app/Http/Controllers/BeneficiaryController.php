<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BeneficiaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = Beneficiary::where('user_id', $userId)
            ->orderByDesc('is_favorite')
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('identifier', 'like', "%{$search}%");
            });
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
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'type' => 'required|in:wallet,bank,airtime,data',
            'identifier' => 'required|string|max:190',
            'bank_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:120',
            'metadata' => 'nullable|array',
            'is_favorite' => 'boolean',
        ]);

        $validator->after(function ($validator) use ($request, $userId) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $exists = Beneficiary::where('user_id', $userId)
                ->where('type', $request->input('type'))
                ->where('identifier', $request->input('identifier'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('identifier', 'This beneficiary already exists.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attributes = $validator->validated();
        $attributes['user_id'] = $userId;
        $attributes['is_favorite'] = $request->boolean('is_favorite');

        $beneficiary = Beneficiary::create($attributes);

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary saved',
            'data' => $beneficiary,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $userId = $this->userId($request);
        $beneficiary = Beneficiary::where('user_id', $userId)->find($id);

        if (! $beneficiary) {
            return response()->json(['success' => false, 'message' => 'Beneficiary not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:120',
            'type' => 'sometimes|required|in:wallet,bank,airtime,data',
            'identifier' => [
                'sometimes',
                'required',
                'string',
                'max:190',
                Rule::unique('beneficiaries')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->where(fn ($query) => $query->where('type', $request->input('type', $beneficiary->type)))
                    ->ignore($beneficiary->id),
            ],
            'bank_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:120',
            'metadata' => 'nullable|array',
            'is_favorite' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attributes = $validator->validated();

        if ($request->has('is_favorite')) {
            $attributes['is_favorite'] = $request->boolean('is_favorite');
        }

        $beneficiary->update($attributes);

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary updated',
            'data' => $beneficiary->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $this->userId($request);
        $beneficiary = Beneficiary::where('user_id', $userId)->find($id);

        if (! $beneficiary) {
            return response()->json(['success' => false, 'message' => 'Beneficiary not found'], 404);
        }

        $beneficiary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary removed',
        ]);
    }

    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $userId = $this->userId($request);
        $beneficiary = Beneficiary::where('user_id', $userId)->find($id);

        if (! $beneficiary) {
            return response()->json(['success' => false, 'message' => 'Beneficiary not found'], 404);
        }

        $beneficiary->update(['is_favorite' => ! $beneficiary->is_favorite]);

        return response()->json([
            'success' => true,
            'message' => $beneficiary->is_favorite ? 'Added to favorites' : 'Removed from favorites',
            'data' => $beneficiary,
        ]);
    }

    protected function userId(Request $request): ?string
    {
        return $request->input('auth_user.id')
            ?? $request->user()['id']
            ?? $request->user('api')['id']
            ?? null;
    }
}
