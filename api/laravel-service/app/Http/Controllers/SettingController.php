<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingService;
use App\Support\Settings\SettingCaster;
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

    public function schema(string $group): JsonResponse
    {
        if (! $this->settingService->hasGroup($group)) {
            return response()->json([
                'success' => false,
                'message' => 'Settings group not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->settingService->schema($group),
        ]);
    }

    public function publicSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settingService->publicSettings(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingService $settingService, string $group): JsonResponse
    {
        if (! $settingService->hasGroup($group)) {
            return response()->json([
                'success' => false,
                'message' => 'Settings group not found',
            ], 404);
        }

        $fields = $request->input('fields', []);

        foreach ($settingService->sensitiveFields($group) as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];

            if ($value === null || $value === '' || $value === SettingCaster::mask((string) $value)) {
                unset($fields[$key]);
            }
        }

        $settingService->update($group, $fields);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $this->settingService->expose($group),
        ]);
    }
}
