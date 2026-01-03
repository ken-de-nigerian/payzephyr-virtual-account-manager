<?php

use PayZephyr\VirtualAccounts\Drivers\FlutterwaveDriver;
use PayZephyr\VirtualAccounts\Drivers\MoniepointDriver;
use PayZephyr\VirtualAccounts\Drivers\ProvidusDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    */
    'default' => env('VIRTUAL_ACCOUNTS_DEFAULT_PROVIDER', 'flutterwave'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'flutterwave' => [
            'driver_class' => FlutterwaveDriver::class,
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
            'enabled' => env('FLUTTERWAVE_ENABLED', true),
        ],

        'monipoint' => [
            'driver_class' => MoniepointDriver::class,
            'api_key' => env('MONIPOINT_API_KEY'),
            'secret_key' => env('MONIPOINT_SECRET_KEY'),
            'contract_code' => env('MONIPOINT_CONTRACT_CODE'),
            'base_url' => env('MONIPOINT_BASE_URL', 'https://api.monnify.com'),
            'enabled' => env('MONIPOINT_ENABLED', false),
        ],

        'providus' => [
            'driver_class' => ProvidusDriver::class,
            'client_id' => env('PROVIDUS_CLIENT_ID'),
            'client_secret' => env('PROVIDUS_CLIENT_SECRET'),
            'base_url' => env('PROVIDUS_BASE_URL', 'https://api.providusbank.com'),
            'enabled' => env('PROVIDUS_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'path' => env('VIRTUAL_ACCOUNTS_WEBHOOK_PATH', '/virtual-accounts/webhook'),
        'verify_signature' => env('VIRTUAL_ACCOUNTS_WEBHOOK_VERIFY', true),
        'rate_limit' => env('VIRTUAL_ACCOUNTS_WEBHOOK_RATE_LIMIT', '120,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('VIRTUAL_ACCOUNTS_LOGGING_ENABLED', true),
        'channel' => env('VIRTUAL_ACCOUNTS_LOG_CHANNEL', 'virtual-accounts'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Configuration
    |--------------------------------------------------------------------------
    */
    'reconciliation' => [
        'enabled' => env('VIRTUAL_ACCOUNTS_RECONCILIATION_ENABLED', true),
        'schedule' => env('VIRTUAL_ACCOUNTS_RECONCILIATION_SCHEDULE', 'daily'),
        'stale_transfer_hours' => env('VIRTUAL_ACCOUNTS_STALE_HOURS', 24),
    ],
];
