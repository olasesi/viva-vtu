<?php

use App\Services\Providers\VtpassProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Aggregator providers
    |--------------------------------------------------------------------------
    | Every provider is an adapter class implementing ProviderContract.
    | Add a new aggregator by registering it here, seeding its service_providers
    | row (is_active = true) and listing it in the routing priority below.
    |
    */

    'providers' => [
        'vtpass' => [
            'class' => VtpassProvider::class,
            'label' => 'VTPass',
            'enabled' => (bool) env('VTPASS_ENABLED', true),
            'base_url' => env('VTPASS_BASE_URL', 'https://vtpass.com/api'),
            'api_key' => env('VTPASS_API_KEY', ''),
            'secret_key' => env('VTPASS_SECRET_KEY', ''),
            'username' => env('VTPASS_USERNAME', ''),
            'password' => env('VTPASS_PASSWORD', ''),
            'endpoints' => [
                'airtime' => '/pay',
                'data' => '/pay',
                'electricity' => '/pay',
                'cable' => '/pay',
                'verify' => '/merchant-verify',
                'requery' => '/requery',
                'service_categories' => '/service-categories',
            ],
        ],

        /*
        | 'recharge' => [
        |     'class' => \App\Services\Providers\RechargeProvider::class,
        |     'label' => 'Recharge.com.ng',
        |     'enabled' => (bool) env('RECHARGE_ENABLED', false),
        |     ...
        | ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing priorities per service category
    |--------------------------------------------------------------------------
    | The first enabled & healthy provider is used. On a definitive rejection
    | (non '000', non '999') the router walks down the list (failover).
    |
    */

    'routing' => [
        'airtime' => ['vtpass'],
        'data' => ['vtpass'],
        'electricity' => ['vtpass'],
        'cable' => ['vtpass'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker / health
    |--------------------------------------------------------------------------
    */

    'health' => [
        'failure_threshold' => (int) env('AGGREGATOR_FAILURE_THRESHOLD', 3),
        'cooldown_minutes' => (int) env('AGGREGATOR_COOLDOWN_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Requery / reconciliation
    |--------------------------------------------------------------------------
    */

    'requery' => [
        'reconcile_minutes' => (int) env('REQUERY_RECONCILE_MINUTES', 15),
        'max_attempts' => (int) env('REQUERY_MAX_ATTEMPTS', 5),
        'retry_delay_minutes' => (int) env('REQUERY_RETRY_DELAY_MINUTES', 2),
    ],

];
