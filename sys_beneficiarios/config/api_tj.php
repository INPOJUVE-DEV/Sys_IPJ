<?php

return [
    'base_url' => env('API_TJ_BASE_URL', 'https://apitj-production.up.railway.app'),
    'timeout' => (int) env('API_TJ_TIMEOUT', 15),

    // Se conserva por compatibilidad, pero la firma oficial sigue siendo RS256.
    'jwt_secret' => env('API_TJ_JWT_SECRET'),
    'curp_hash_secret' => env('API_TJ_CURP_HASH_SECRET', env('CURP_HASH_SECRET', '')),

    'inbound' => [
        'issuer' => env('API_TJ_ISSUER', 'api_tj'),
        'audience' => env('API_TJ_AUDIENCE', 'sys_ipj'),
        'allowed_scope' => env('API_TJ_ALLOWED_SCOPE', env('API_TJ_ALLOWED_SCOPES', 'beneficiarios.create')),
        'public_key' => env('API_TJ_PUBLIC_KEY', ''),
        'jwt_kid' => env('API_TJ_JWT_KID', 'api_tj-current'),
    ],

    'outbound' => [
        'issuer' => env('SYS_IPJ_ISSUER', env('API_TJ_CLIENT_CODE', 'sys_ipj')),
        'subject' => env('SYS_IPJ_SUBJECT', env('API_TJ_CLIENT_CODE', 'sys_ipj')),
        'audience' => env('SYS_IPJ_AUDIENCE', 'api_tj'),
        'scope' => env('SYS_IPJ_SCOPE', 'cardholders.sync'),
        'jwt_kid' => env('SYS_IPJ_JWT_KID', 'sys_ipj-current'),
        'private_key_path' => env('API_TJ_PRIVATE_KEY_PATH', storage_path('app/keys/sys_ipj_private.pem')),
        'sync_path' => env('API_TJ_SYNC_PATH', '/api/v1/cardholders/sync'),
    ],
];
