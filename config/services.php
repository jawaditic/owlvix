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

    // Google Analytics 4, e.g. G-ABC123XYZ. Leave empty to turn it off (e.g. locally).
    'google_analytics' => [
        'id' => env('GA_MEASUREMENT_ID'),
    ],

    // Microsoft Clarity (session recordings + heatmaps). Leave empty to turn it off.
    'clarity' => [
        'id' => env('CLARITY_PROJECT_ID'),
    ],

    'contentsquare' => [
        'id' => env('CONTENTSQUARE_TAG_ID'),
    ],

    // IndexNow (Bing etc.): any 32-character code; it's also published at https://owlvix.com/<key>.txt
    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
    ],

    // Google reCAPTCHA v3 (invisible). Leave the keys empty to turn it off, e.g. locally.
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        // 0.0 = very likely a bot, 1.0 = very likely a person. Google suggests 0.5.
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

];
