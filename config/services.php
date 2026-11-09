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
        'api_key' => env('HRIS_API_KEY'),
        'timeout' => (int) env('HRIS_TIMEOUT', 10),
    ],

    // PGSO's property management system, read by `php artisan pms:sync`
    // to counter-check inventory (docs/inventory-reconciliation.md). It
    // returns every property in one response (~30 s), hence the timeout.
    'pms' => [
        'base_url' => env('PMS_BASE_URL'),
        'api_key' => env('PMS_API_KEY'),
        'timeout' => (int) env('PMS_TIMEOUT', 180),
        // The PMS "office" items handed back to PGSO are listed under.
        'returned_office' => env('PMS_RETURNED_OFFICE', 'PGSO-RETURNED'),
    ],

    // Moved to config/hris.php — EmployeeController::search() reads
    // config('hris.employee_filters'), not config('services.employee_filters').

];
