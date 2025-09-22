<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('APP_IPJ_URL', 'http://localhost:5173'),
        env('APP_IPJ_PROD_URL', 'https://app.ipj.example'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag'],

    'max_age' => 0,

    'supports_credentials' => false,
];
