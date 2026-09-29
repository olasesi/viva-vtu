<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthAlertService
{
    public function providerTripped(string $slug, int $consecutiveFailures): void
    {
        if (! config('aggregators.health.alert_enabled', false)) {
            return;
        }

        $url = config('aggregators.health.alert_url');

        if (! $url) {
            return;
        }

        $payload = [
            'text' => sprintf(
                "*[%s] Aggregator circuit breaker tripped*\nSlug: `%s`\nConsecutive failures: %d\nEnv: %s",
                config('app.name', 'VIVAVTU'),
                $slug,
                $consecutiveFailures,
                app()->environment(),
            ),
        ];

        try {
            Http::timeout(10)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Health alert delivery failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
