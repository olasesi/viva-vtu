<?php

return [

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

    'networks' => [
        'mtn' => 'mtn',
        'glo' => 'glo',
        'airtel' => 'airtel',
        '9mobile' => '9mobile',
    ],

    'meter_types' => [
        'prepaid' => 'prepaid',
        'postpaid' => 'postpaid',
    ],

    'retry' => [
        'max_attempts' => 3,
        'delay_ms' => 1000,
    ],

];
