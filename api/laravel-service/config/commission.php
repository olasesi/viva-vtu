<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission engine
    |--------------------------------------------------------------------------
    | Referral commissions are credited to a customer's referrer when the
    | referred customer completes a successful, spend-category purchase
    | (airtime, data, electricity, cable, education, streaming). Transfers,
    | funding and reversals never generate commissions.
    |
    */

    'referral' => [
        'enabled' => (bool) env('REFERRAL_COMMISSIONS_ENABLED', true),
        'rate' => (float) env('REFERRAL_COMMISSION_RATE', 0.01),
        'min_amount' => (float) env('REFERRAL_MIN_COMMISSION', 1.0),
        'categories' => ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'],
    ],

];
