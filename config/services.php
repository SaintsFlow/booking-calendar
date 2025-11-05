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

    /*
    |--------------------------------------------------------------------------
    | CRM Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for external CRM system integration.
    | Events will be sent asynchronously via background jobs.
    |
    */

    'crm' => [
        'enabled' => env('CRM_ENABLED', false),
        'webhook_url' => env('CRM_WEBHOOK_URL'),
        'token' => env('CRM_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bitrix24 CRM Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Bitrix24 CRM REST API integration.
    |
    */

    'bitrix24' => [
        'enabled' => env('BITRIX24_ENABLED', false),
        'webhook_url' => env('BITRIX24_WEBHOOK_URL'),

        // OAuth 2.0 credentials
        'client_id' => env('BITRIX24_CLIENT_ID'),
        'client_secret' => env('BITRIX24_CLIENT_SECRET'),

        // Contact settings
        'contact' => [
            'type_id' => env('BITRIX24_CONTACT_TYPE_ID', 'CLIENT'),
            'source_id' => env('BITRIX24_CONTACT_SOURCE_ID', 'WEBFORM'),
            'honorific' => env('BITRIX24_CONTACT_HONORIFIC', null),
            'opened' => env('BITRIX24_CONTACT_OPENED', 'Y'),
        ],

        // Deal settings
        'deal' => [
            'category_id' => env('BITRIX24_DEAL_CATEGORY_ID', 0), // Воронка
            'stage_id' => env('BITRIX24_DEAL_STAGE_ID', 'NEW'), // Стадия
            'type_id' => env('BITRIX24_DEAL_TYPE_ID', 'SALE'),
            'source_id' => env('BITRIX24_DEAL_SOURCE_ID', 'WEBFORM'),
            'currency_id' => env('BITRIX24_DEAL_CURRENCY_ID', 'RUB'),
            'opened' => env('BITRIX24_DEAL_OPENED', 'Y'),
            'probability' => env('BITRIX24_DEAL_PROBABILITY', 50),
        ],

        // Search limits
        'limits' => [
            'max_contacts_for_deal_search' => env('BITRIX24_MAX_CONTACTS_FOR_DEAL_SEARCH', 10),
            'max_duplicate_values' => env('BITRIX24_MAX_DUPLICATE_VALUES', 20),
        ],
    ],

];
