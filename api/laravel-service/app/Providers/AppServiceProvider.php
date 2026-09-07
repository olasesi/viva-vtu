<?php

namespace App\Providers;

use App\Services\Providers\ProviderContract;
use App\Services\Providers\VtpassProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VtpassProvider::class, function () {
            return new VtpassProvider(config('aggregators.providers.vtpass', []));
        });

        $this->app->bind(ProviderContract::class, VtpassProvider::class);
    }

    public function boot(): void
    {
        Model::unguard(false);
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
