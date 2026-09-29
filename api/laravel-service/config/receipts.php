<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transaction receipt emission
    |--------------------------------------------------------------------------
    | Emails customers a receipt whenever a transaction settles (successful
    | purchase, refund, wallet funding, money received). Driven from the
    | runtime `email` settings group; nothing is sent until `mail_from_address`
    | is configured in the admin settings.
    |
    */

    'email' => [
        'enabled' => (bool) env('RECEIPTS_EMAIL_ENABLED', true),
    ],

];
