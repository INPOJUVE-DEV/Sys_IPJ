<?php

return [
    'payload_encryption_key' => env('INTEGRATION_PAYLOAD_ENCRYPTION_KEY'),

    'api_tj' => [
        'integration_user_email' => env('API_TJ_INTEGRATION_USER_EMAIL', 'integracion.api_tj@inpojuve.local'),
    ],

    'inbound' => [
        'audience' => env('SYS_IPJ_INTEGRATION_AUDIENCE', 'sys_ipj'),
        'max_token_ttl_seconds' => (int) env('SYS_IPJ_JWT_TTL_SECONDS', 600),
    ],

    'outbound' => [
        'base_url' => env('API_TJ_BASE_URL', 'http://api-tj.test'),
        'issuer' => env('SYS_IPJ_JWT_ISSUER', 'sys_ipj'),
        'subject' => env('SYS_IPJ_JWT_SUBJECT', 'sys_ipj'),
        'audience' => env('API_TJ_AUDIENCE', 'api_tj'),
        'scope' => env('SYS_IPJ_SCOPE', 'cardholders.sync'),
        'kid' => env('SYS_IPJ_JWT_KID', 'sys_ipj-current'),
        'private_key_path' => env('SYS_IPJ_PRIVATE_KEY_PATH', storage_path('app/keys/sys_ipj_private.pem')),
        'ttl_seconds' => (int) env('SYS_IPJ_JWT_TTL_SECONDS', 600),
        'hash_secret' => env('CURP_HASH_SECRET'),
        'timeout_seconds' => (int) env('API_TJ_SYNC_TIMEOUT_SECONDS', 15),
        'batch_size' => max(1, (int) env('API_TJ_SYNC_BATCH_SIZE', 100)),
    ],
];
