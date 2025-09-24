<?php

// config for SabitAhmad/SteadFast
return [
    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Steadfast API credentials. You can get these from your Steadfast
    | merchant panel.
    |
    */
    'api_key' => env('STEADFAST_API_KEY'),
    'secret_key' => env('STEADFAST_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
    'timeout' => env('STEADFAST_TIMEOUT', 30),
    'connect_timeout' => env('STEADFAST_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Bulk Order Configuration
    |--------------------------------------------------------------------------
    */
    'bulk' => [
        'queue' => env('STEADFAST_BULK_QUEUE', true),
        'chunk_size' => env('STEADFAST_BULK_CHUNK_SIZE', 500),
        'queue_name' => env('STEADFAST_QUEUE_NAME', 'default'),
        'queue_connection' => env('STEADFAST_QUEUE_CONNECTION', null),
        'max_attempts' => env('STEADFAST_BULK_MAX_ATTEMPTS', 3),
        'backoff_seconds' => env('STEADFAST_BULK_BACKOFF', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'times' => env('STEADFAST_RETRY_TIMES', 3),
        'sleep' => env('STEADFAST_RETRY_SLEEP', 1000), // milliseconds
        'when' => [
            // Retry on these HTTP status codes
            500, 502, 503, 504, 429,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('STEADFAST_LOGGING', true),
        'log_level' => env('STEADFAST_LOG_LEVEL', 'error'),
        'log_requests' => env('STEADFAST_LOG_REQUESTS', false),
        'log_responses' => env('STEADFAST_LOG_RESPONSES', true),
        'cleanup_logs' => env('STEADFAST_CLEANUP_LOGS', true),
        'keep_logs_days' => env('STEADFAST_KEEP_LOGS_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('STEADFAST_CACHE_ENABLED', false),
        'ttl' => env('STEADFAST_CACHE_TTL', 300), // 5 minutes
        'prefix' => env('STEADFAST_CACHE_PREFIX', 'steadfast'),
        'store' => env('STEADFAST_CACHE_STORE', null), // null = default cache store
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'strict_phone' => env('STEADFAST_STRICT_PHONE', true),
        'require_email' => env('STEADFAST_REQUIRE_EMAIL', false),
        'max_invoice_length' => env('STEADFAST_MAX_INVOICE_LENGTH', 255),
        'max_address_length' => env('STEADFAST_MAX_ADDRESS_LENGTH', 250),
        'max_name_length' => env('STEADFAST_MAX_NAME_LENGTH', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'delivery_type' => env('STEADFAST_DEFAULT_DELIVERY_TYPE', 0), // 0 = home, 1 = point
        'cod_amount' => env('STEADFAST_DEFAULT_COD_AMOUNT', 0),
    ],
];
