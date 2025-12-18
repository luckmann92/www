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

    'genapi' => [
        'api_key' => env('GENAPI_API_KEY', ''),
        'endpoint' => env('GENAPI_ENDPOINT', 'https://api.gen-api.ru/api/v1/networks/gemini-flash-image'),
        'use_genapi' => env('USE_GENAPI_SERVICE', false),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY', ''),
        'endpoint' => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'gpt-image-1'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    ],

    'payment_provider' => [
        'key' => env('PAYMENT_PROVIDER_KEY', ''),
        'base_url' => env('PAYMENT_PROVIDER_BASE_URL', 'https://api.payment.example.com'),
    ],

    'yookassa' => [
        'shop_id' => env('YOOKASSA_SHOP_ID', ''),
        'secret_key' => env('YOOKASSA_SECRET_KEY', ''),
        'api_key' => env('YOOKASSA_API_KEY', ''),
    ],

    'alfabank' => [
        'username' => env('ALFABANK_USERNAME', ''),
        'password' => env('ALFABANK_PASSWORD', ''),
        'base_url' => env('ALFABANK_BASE_URL', 'https://alfa.rbsuat.com/payment/rest'),
    ],

];
