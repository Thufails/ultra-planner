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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'nominatim' => [
        'endpoint' => env('NOMINATIM_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env(
            'NOMINATIM_USER_AGENT',
            'UltraPlanner/1.0 (+' . env('APP_URL', 'http://localhost') . ')',
        ),
    ],

    'routing' => [
        'endpoint' => env('ROUTING_ENDPOINT', 'https://routing.openstreetmap.de/routed-foot/route/v1/driving'),
        'user_agent' => env(
            'ROUTING_USER_AGENT',
            'UltraPlanner/1.0 (+' . env('APP_URL', 'http://localhost') . ')',
        ),
    ],

    'elevation' => [
        'endpoint' => env('ELEVATION_ENDPOINT', 'https://api.opentopodata.org/v1/srtm30m'),
        'user_agent' => env(
            'ELEVATION_USER_AGENT',
            'UltraPlanner/1.0 (+' . env('APP_URL', 'http://localhost') . ')',
        ),
    ],

];
