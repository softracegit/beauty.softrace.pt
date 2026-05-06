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

    'resend' => [
        'key' => env('RESEND_KEY'),
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
        'account_sid' => env('APP_ENV') === 'local'
            ? env('TWILIO_ACCOUNT_SID_SANDBOX', env('TWILIO_ACCOUNT_SID', env('TWILIO_SID')))
            : env('TWILIO_ACCOUNT_SID', env('TWILIO_SID')),
        'auth_token' => env('APP_ENV') === 'local'
            ? env('TWILIO_AUTH_TOKEN_SANDBOX', env('TWILIO_AUTH_TOKEN', env('TWILIO_TOKEN')))
            : env('TWILIO_AUTH_TOKEN', env('TWILIO_TOKEN')),
        'sms_from' => env('TWILIO_SMS_FROM', env('TWILIO_FROM')),
    ],

    'vendus' => [
        'api_key' => env('VENDUS_API_KEY'),
        'base_url' => env('VENDUS_BASE_URL'),
        // Opcoes: bearer, basic ou query
        'auth_mode' => env('VENDUS_AUTH_MODE', 'bearer'),
        'documents_endpoint' => env('VENDUS_DOCUMENTS_ENDPOINT', '/documents/'),
        'document_type' => env('VENDUS_DOCUMENT_TYPE', 'FT'),
        'tax_id' => env('VENDUS_TAX_ID', 'NOR'),
        'category_id' => env('VENDUS_CATEGORY_ID'),
        'category_title' => env('VENDUS_CATEGORY_TITLE', 'Serviços'),
        'register_id' => env('VENDUS_REGISTER_ID'),
        'mode' => env('VENDUS_MODE', 'normal'),
        'simulate' => env('VENDUS_SIMULATE', false),
    ],

];
