<?php

namespace App\Services;

use App\Models\ServiceProvider;
use App\Services\Providers\ProviderContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProviderRouter
{
    protected const FAILURE_KEY = 'aggregator_failures:%s';

    protected const TRIPPED_KEY = 'aggregator_tripped_at:%s';

    /**
     * Enabled, healthy provider instances for a category, in priority order.
     *
     * @return array<string, ProviderContract>
     */
    public function providersFor(string $category): array
    {
        $providers = [];

        foreach ((array) config('aggregators.routing.'.$category, []) as $slug) {
            if (! $this->isEnabled($slug) || ! $this->isHealthy($slug)) {
                continue;
            }

            $instance = $this->resolve($slug);
            if ($instance) {
                $providers[$slug] = $instance;
            }
        }

        return $providers;
    }

    public function resolve(string $slug): ?ProviderContract
    {
        $config = config('aggregators.providers.'.$slug);

        if (! $config || ! isset($config['class']) || ! class_exists($config['class'])) {
            Log::warning('Aggregator provider not configured', ['slug' => $slug]);

            return null;
        }

        return new $config['class']($config);
    }

    public function markSuccess(string $slug): void
    {
        Cache::forget(sprintf(self::FAILURE_KEY, $slug));
        Cache::forget(sprintf(self::TRIPPED_KEY, $slug));
    }

    public function markFailure(string $slug): void
    {
        $key = sprintf(self::FAILURE_KEY, $slug);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, 86400);

        if (! $this->isTripped($slug) && $count >= (int) config('aggregators.health.failure_threshold', 3)) {
            Cache::put(sprintf(self::TRIPPED_KEY, $slug), now()->getTimestamp(), 86400);

            Log::warning('Aggregator circuit breaker tripped', [
                'slug' => $slug,
                'consecutive_failures' => $count,
            ]);
        }
    }

    protected function isEnabled(string $slug): bool
    {
        $row = ServiceProvider::where('slug', $slug)->first();

        if ($row) {
            return (bool) $row->is_active;
        }

        return (bool) (config('aggregators.providers.'.$slug.'.enabled', true));
    }

    protected function isHealthy(string $slug): bool
    {
        $failures = (int) Cache::get(sprintf(self::FAILURE_KEY, $slug), 0);

        if ($failures < (int) config('aggregators.health.failure_threshold', 3)) {
            return true;
        }

        if (! $this->isTripped($slug)) {
            return true;
        }

        $cooldownSeconds = (int) config('aggregators.health.cooldown_minutes', 5) * 60;

        if (now()->getTimestamp() - (int) Cache::get(sprintf(self::TRIPPED_KEY, $slug)) >= $cooldownSeconds) {
            Cache::forget(sprintf(self::FAILURE_KEY, $slug));
            Cache::forget(sprintf(self::TRIPPED_KEY, $slug));

            Log::info('Aggregator circuit breaker reset', ['slug' => $slug]);

            return true;
        }

        return false;
    }

    protected function isTripped(string $slug): bool
    {
        return is_numeric(Cache::get(sprintf(self::TRIPPED_KEY, $slug)));
    }

    public function healthyProvidersReport(): array
    {
        $report = [];

        foreach ((array) config('aggregators.providers') as $slug => $config) {
            $report[$slug] = [
                'enabled' => $this->isEnabled($slug),
                'healthy' => $this->isHealthy($slug),
                'consecutive_failures' => (int) Cache::get(sprintf(self::FAILURE_KEY, $slug), 0),
            ];
        }

        return $report;
    }
}
