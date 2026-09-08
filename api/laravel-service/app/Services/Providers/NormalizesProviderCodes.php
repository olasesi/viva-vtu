<?php

namespace App\Services\Providers;

trait NormalizesProviderCodes
{
    /**
     * Resolve a provider_code for a category/network from config, falling back
     * to "{key}-{category}" when no mapping is configured.
     */
    protected function code(string $category, string $key): string
    {
        $map = $this->config['provider_codes'][$category] ?? [];

        $fallback = str_contains($key, '-') ? $key : $key.'-'.$category;

        return (string) ($map[$key] ?? $fallback);
    }
}
