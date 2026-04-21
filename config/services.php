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

    // API Services
    'hris' => [
        'base_url' => env('HRIS_BASE_URL'),
        'api_key'  => env('HRIS_API_KEY'),
        'timeout'  => (int) env('HRIS_TIMEOUT', 10),
    ],

    'employee_filters' => [
        'employee_id' => [
            'type' => 'string',
            'min' => 6,
        ],
        'office_id' => [
            'type' => 'int',
        ],
        'type' => [
            'type' => 'string',
            'allowed' => ['permanent', 'casual', 'contract', 'job_order'],
        ],

        // future:
        // 'fullname' => ['type' => 'string', 'min' => 2],
    ],

];
