<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ocr_ine' => [
        'url'     => env('OCR_INE_SERVICE_URL', 'http://localhost:8001'),
        'api_key' => env('OCR_INE_API_KEY', ''),
        'timeout' => 15,
    ],

    'api_tj' => [
        'base_url' => env('API_TJ_BASE_URL', 'https://apitj-production.up.railway.app'),
        'audience' => env('API_TJ_AUDIENCE', 'sys_ipj'),
        'allowed_scope' => env('API_TJ_ALLOWED_SCOPES', 'beneficiarios.create'),
        'public_key' => env('API_TJ_PUBLIC_KEY', ''),
        'jwt_kid' => env('API_TJ_JWT_KID', 'api_tj-current'),
        'timeout' => (int) env('API_TJ_TIMEOUT', 15),
    ],

    'sys_ipj' => [
        'audience' => env('SYS_IPJ_AUDIENCE', 'api_tj'),
        'scope' => env('SYS_IPJ_SCOPE', 'cardholders.sync'),
        'client_code' => env('API_TJ_CLIENT_CODE', 'sys_ipj'),
        'jwt_kid' => env('SYS_IPJ_JWT_KID', 'sys_ipj-current'),
        'private_key_path' => env('API_TJ_PRIVATE_KEY_PATH', storage_path('app/keys/sys_ipj_private.pem')),
        'curp_hash_secret' => env('CURP_HASH_SECRET', ''),
    ],

];
