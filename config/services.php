<?php

return [
    'novaspins' => [
        'hmac_secret' => env('NOVASPINS_HMAC_SECRET'),
        'default_currency' => env('NOVASPINS_DEFAULT_CURRENCY', 'BRL'),
        'callback_rate_limit' => env('NOVASPINS_CALLBACK_RATE_LIMIT', 120),
        'replay_rate_limit' => env('NOVASPINS_REPLAY_RATE_LIMIT', 10),
    ],
];
