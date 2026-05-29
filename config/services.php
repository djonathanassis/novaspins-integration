<?php

return [
    'novaspins' => [
        'hmac_secret' => env('NOVASPINS_HMAC_SECRET'),
        'default_currency' => env('NOVASPINS_DEFAULT_CURRENCY', 'BRL'),
    ],
];
