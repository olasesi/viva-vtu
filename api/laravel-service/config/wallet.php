<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transaction PIN threshold
    |--------------------------------------------------------------------------
    |
    | When a user has configured a transaction PIN, the PIN is required for
    | money-moving operations at or above this amount (in NGN). Set to 0 to
    | require the PIN for every operation once it has been configured.
    |
    */
    'transaction_pin_threshold' => (float) env('TRANSACTION_PIN_THRESHOLD', 0),
];
