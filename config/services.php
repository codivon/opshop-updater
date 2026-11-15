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


    /*
    |--------------------------------------------------------------------------
    | پاکت لایسنس امضاشده (Signed License Envelope) — سخت‌سازی امنیتی فاز ۱
    |--------------------------------------------------------------------------
    | کلید خصوصی EC P-256 برای امضای پاکت لایسنس و مانیفست یکپارچگی فایل‌ها.
    | کلید عمومیِ همان جفت‌کلید در کد کلاینت (پروژهٔ فروشگاه) جاسازی می‌شود.
    | ساخت جفت‌کلید:
    |   openssl ecparam -name prime256v1 -genkey -noout -out private.pem
    |   openssl ec -in private.pem -pubout -out public.pem
    */
    'license_envelope' => [
        'private_key'          => env('PACKAGE_LICENSE_SIGNING_KEY'),
        'grace_hours'          => env('PACKAGE_LICENSE_GRACE_HOURS', 24),
        'check_interval_hours' => env('PACKAGE_LICENSE_CHECK_INTERVAL_HOURS', 24),
    ],

];
