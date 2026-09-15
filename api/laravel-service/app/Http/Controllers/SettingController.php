<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(): JsonResponse
    {
        $groups = collect($this->settingService->groups())
            ->map(fn (string $group) => $this->settingService->expose($group))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    public function show(string $group): JsonResponse
    {
        if (! $this->settingService->hasGroup($group)) {
            return response()->json([
                'success' => false,
                'message' => 'Settings group not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->settingService->expose($group),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingService $settingService, string $group): JsonResponse
    {
        $settingService->update($group, $request->input('fields'));

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $this->settingService->expose($group),
        ]);
    }
}
