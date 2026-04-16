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

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_TAC_ENABLED', false),
        'provider' => env('WHATSAPP_TAC_PROVIDER', 'wasender'),
        'twilio_from' => env('WHATSAPP_TWILIO_FROM', 'whatsapp:+14155238886'),
        'debug_show_tac' => env('WHATSAPP_TAC_DEBUG_SHOW', false),
    ],

    'wasender' => [
        'enabled' => env('WASENDER_ENABLED', false),
        'base_url' => env('WASENDER_BASE_URL', 'https://www.wasenderapi.com/api'),
        'api_key' => env('WASENDER_API_KEY'),
        'webhook_secret' => env('WASENDER_WEBHOOK_SECRET'),
    ],

    'toyyibpay' => [
        // Supports both current and legacy env key names used in older projects.
        'base_url' => env('TOYYIBPAY_BASE_URL', env('TOYYIBPAY_BASEURL', 'https://toyyibpay.com')),
        'user_secret_key' => env('TOYYIBPAY_USER_SECRET_KEY', env('TOYYIBPAY_SECRET_KEY')),
        'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
        'payment_channel' => env('TOYYIBPAY_PAYMENT_CHANNEL', '0'),
        'charge_to_customer' => env('TOYYIBPAY_CHARGE_TO_CUSTOMER', ''),
    ],
    'parent_tester_phones' => array_values(array_filter(array_map(
        static fn ($phone) => trim((string) $phone),
        explode(',', (string) env('PARENT_TESTER_PHONES', '60136454001'))
    ))),

    'parent_tester_amount' => (float) env('PARENT_TESTER_AMOUNT', 1),

    'teacher_whatsapp_phone' => env('TEACHER_WHATSAPP_PHONE', '60123103205'),

    'treasury_whatsapp_phone' => env('TREASURY_WHATSAPP_PHONE', '60136454001'),
];

