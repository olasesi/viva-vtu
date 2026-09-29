<?php

use App\Services\Providers\AidaPayProvider;
use App\Services\Providers\EasyAccessProvider;
use App\Services\Providers\RechargeProvider;
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

        'aidapay' => [
            'class' => AidaPayProvider::class,
            'label' => 'AidaPay',
            'enabled' => (bool) env('AIDAPAY_ENABLED', false),
            'base_url' => env('AIDAPAY_BASE_URL', 'https://www.aidapay.ng/api/v1'),
            'api_token' => env('AIDAPAY_API_TOKEN', ''),
            'account_pin' => env('AIDAPAY_ACCOUNT_PIN', ''),
            'provider_codes' => [
                'airtime' => [
                    'mtn' => 'mtn-airtime',
                    'glo' => 'glo-airtime',
                    'airtel' => 'airtel-airtime',
                    '9mobile' => '9mobile-airtime',
                ],
                'data' => [
                    'mtn' => 'mtn-data',
                    'glo' => 'glo-data',
                    'airtel' => 'airtel-data',
                    '9mobile' => '9mobile-data',
                ],
                'electricity' => [
                    'ikeja-electric' => 'ikeja-electric',
                    'eko-electric' => 'eko-electric',
                    'abuja-electric' => 'abuja-electric',
                    'enugu-electric' => 'enugu-electric',
                    'ibadan-electric' => 'ibadan-electric',
                    'kaduna-electric' => 'kaduna-electric',
                    'kano-electric' => 'kano-electric',
                    'portharcourt-electric' => 'portharcourt-electric',
                    'jos-electric' => 'jos-electric',
                    'benin-electric' => 'benin-electric',
                ],
                'cable' => [
                    'dstv' => 'dstv',
                    'gotv' => 'gotv',
                    'startimes' => 'startimes',
                ],
                'education' => [
                    'waec' => 'waec',
                    'neco' => 'neco',
                    'nabteb' => 'nabteb',
                    'nbais' => 'nbais',
                ],
                'streaming' => [
                    'netflix' => 'netflix',
                    'showmax' => 'showmax',
                    'dstv-stream' => 'dstv-stream',
                ],
            ],
            'webhook' => [
                'path' => '/webhook/aidapay',
                'signature_header' => 'Signature',
                'secret_from' => 'api_token',
                'successful_statuses' => ['Completed'],
                'processing_statuses' => ['Processing', 'Pending'],
                'failed_statuses' => ['Refund', 'Cancelled'],
                'status_path' => 'status',
                'reference_path' => 'ref',
                'hash_path' => 'transaction_hash',
            ],
        ],

        'easyaccess' => [
            'class' => EasyAccessProvider::class,
            'label' => 'EasyAccess',
            'enabled' => (bool) env('EASYACCESS_ENABLED', false),
            'base_url' => env('EASYACCESS_BASE_URL', 'https://easyaccessapi.com.ng'),
            'api_token' => env('EASYACCESS_API_TOKEN', ''),
            'endpoints' => [
                'airtime' => '/api/airtime',
                'data' => '/api/data',
                'electricity' => '/api/electricity',
                'electricity_verify' => '/api/electricity/verify',
                'cable' => '/api/tv',
                'cable_verify' => '/api/tv/verify',
                'exam' => '/api/exam',
                'streaming' => '/api/streaming',
                'requery' => '/api/requery',
                'balance' => '/api/balance',
            ],
            'webhook' => [
                'path' => '/webhook/easyaccess',
                'signature_header' => 'X-Easyaccess-Signature',
                'successful_statuses' => ['SUCCESS', 'successful', 'Completed'],
                'processing_statuses' => ['PENDING', 'processing', 'Processing'],
                'failed_statuses' => ['FAILED', 'failed', 'REFUNDED', 'Refund'],
                'status_path' => 'status',
                'reference_path' => 'reference',
                'hash_path' => 'transaction_id',
            ],
        ],

        'recharge' => [
            'class' => RechargeProvider::class,
            'label' => 'Recharge.com.ng',
            'enabled' => (bool) env('RECHARGE_ENABLED', false),
            'base_url' => env('RECHARGE_BASE_URL', 'https://nigeria.recharge.com.ng/api'),
            'api_token' => env('RECHARGE_API_TOKEN', ''),
            'success_codes' => ['000', '0', '200'],
            'endpoints' => [
                'airtime' => '/airtime',
                'data' => '/data',
                'electricity' => '/electricity',
                'cable' => '/tv',
                'exam' => '/exam',
                'streaming' => '/streaming',
                'verify' => '/verify',
                'requery' => '/status',
            ],
        ],
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
        'airtime' => ['vtpass', 'aidapay', 'easyaccess', 'recharge'],
        'data' => ['vtpass', 'aidapay', 'easyaccess', 'recharge'],
        'electricity' => ['vtpass', 'aidapay', 'easyaccess', 'recharge'],
        'cable' => ['vtpass', 'aidapay', 'easyaccess', 'recharge'],
        'education' => ['vtpass', 'aidapay', 'easyaccess', 'recharge'],
        'streaming' => ['vtpass', 'aidapay', 'easyaccess', 'recharge'],
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
    | Best-effort meter/smartcard pre-validation
    |--------------------------------------------------------------------------
    | When enabled (runtime setting `pre_validation_enabled` under the `api`
    | group), electricity meters and cable smartcards are verified against the
    | provider before the wallet is debited. Only a definitive provider
    | rejection blocks the purchase; an unreachable provider never does.
    |
    */

    'pre_validation' => [
        'categories' => ['electricity', 'cable'],
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
