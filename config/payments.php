<?php

return [
    'authorize_net' => [
        'api_login_id' => env('AUTHORIZE_NET_API_LOGIN_ID'),
        'transaction_key' => env('AUTHORIZE_NET_TRANSACTION_KEY'),
        'sandbox' => env('AUTHORIZE_NET_SANDBOX', true),
        // When credentials are missing, charge() simulates a successful sandbox payment.
        'allow_stub' => env('AUTHORIZE_NET_ALLOW_STUB', true),
    ],
];
