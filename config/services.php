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

    // Serviços externos do motor financeiro.
    'exchange_rate' => [
        'url' => env('EXCHANGE_RATE_URL', 'https://api.exchangerate.host/latest'),
        'connect_timeout' => env('EXCHANGE_RATE_CONNECT_TIMEOUT', 5),
        'timeout' => env('EXCHANGE_RATE_TIMEOUT', 10),
    ],

    'market_data' => [
        'url' => env('MARKET_DATA_URL', 'https://pro-api.coinmarketcap.com/v2/cryptocurrency/quotes/latest'),
        'api_key' => env('COIN_MARKETCAP_API_KEY'),
        'connect_timeout' => env('MARKET_DATA_CONNECT_TIMEOUT', 5),
        'timeout' => env('MARKET_DATA_TIMEOUT', 10),
    ],

];
