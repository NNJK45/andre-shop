<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'nokash' => [
        'payin_url' => env('NOKASH_PAYIN_URL', 'https://api.nokash.app/lapas-on-trans/trans/api-payin-request/407'),
        'status_url' => env('NOKASH_STATUS_URL', 'https://api.nokash.app/lapas-on-trans/trans/310/status-request'),
        'callback_url' => env('NOKASH_CALLBACK_URL'),
        'i_space_key' => env('NOKASH_I_SPACE_KEY'),
        'app_space_key' => env('NOKASH_APP_SPACE_KEY'),
        'timeout_seconds' => (int) env('NOKASH_TIMEOUT_SECONDS', 30),
    ],
];
