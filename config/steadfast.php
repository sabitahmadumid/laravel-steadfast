<?php

// config for SabitAhmad/SteadFast
return [
    'api_key' => env('STEADFAST_API_KEY'),
    'secret_key' => env('STEADFAST_SECRET_KEY'),
    'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),

    'bulk' => [
        'queue' => env('STEADFAST_BULK_QUEUE', true),
        'chunk_size' => 500,
        'queue_name' => env('STEADFAST_QUEUE_NAME', 'default'),
    ],

    'timeout' => 30,
    'retry' => [
        'times' => 3,
        'sleep' => 100,
    ],

    'logging' => [
        'enabled' => env('STEADFAST_LOGGING', true),
        'log_level' => env('STEADFAST_LOG_LEVEL', 'error'),
    ],
];
