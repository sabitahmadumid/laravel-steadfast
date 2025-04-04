# Unofficial Laravel SDK wrapper for SteadFast courier with Bulk order creation using queue

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sabitahmad/laravel-steadfast.svg?style=flat-square)](https://packagist.org/packages/sabitahmad/laravel-steadfast)
[![Total Downloads](https://img.shields.io/packagist/dt/sabitahmad/laravel-steadfast.svg?style=flat-square)](https://packagist.org/packages/sabitahmad/laravel-steadfast)

A feature-rich Laravel package designed to seamlessly integrate with Steadfast Courier's delivery services API.
## Key Features

- 📦 Individual order creation with validation
- 🔀 Bulk order processing with queue management
- 🔍 Real-time shipment status tracking
- 💵 Account balance verification
- 📑 Detailed activity logs
- 🔁 Built-in retry mechanism
- ⚙️ Customizable batch processing
- 🛡️ Type-safe data transfer objects


## Getting Started
## Installation

You can install the package via composer:

```bash
composer require sabitahmad/laravel-steadfast
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-steadfast-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-steadfast-config"
```

This is the contents of the published config file:

```php
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
```

# Environment Setup

```dotenv
STEADFAST_API_KEY=your_api_key_here
STEADFAST_SECRET_KEY=your_secret_key_here
STEADFAST_BULK_QUEUE=true
STEADFAST_LOGGING=true
```

## Usage

### Implementation Guide

**`Create single order`**

```php

$deliveryOrder = new OrderRequest(
    invoice: 'INV-2023-001',
    recipient_name: 'Recipient Name',
    recipient_phone: '01234567890',
    recipient_address: 'Delivery Address',
    cod_amount: 2000.00,
    note: 'Special instructions'
);

$response = Steadfast::createOrder($deliveryOrder);

```

`Bulk Order Processing`

```php
$orders = [
    new OrderRequest([
        invoice: 'INV-2023-001',
        recipient_name: 'Recipient Name',
        recipient_phone: '01234567890',
        recipient_address: 'Delivery Address',
        cod_amount: 2000.00,
        note: 'Special instructions'
    ]),
    new OrderRequest([
        invoice: 'INV-2023-002',
        recipient_name: 'Another Recipient',
        recipient_phone: '09876543210',
        recipient_address: 'Another Address',
        cod_amount: 1500.00,
        note: 'Additional instructions'
    ]),
    // ...
];

    // Process via queue (default)
    Steadfast::bulkCreate($orders);

    // Process immediately
    $response = Steadfast::bulkCreate($orders, false);
```

`Checking Status`

```php
// By tracking code
$status = Steadfast::checkStatusByTrackingCode('TRACK123');

// By invoice number
$status = Steadfast::checkStatusByInvoice('ORDER-123');

// By consignment ID
$status = Steadfast::checkStatusByConsignmentId(1424107);
```

`Checking Balance`

```php
$balance = Steadfast::getBalance();
```

## Advanced Configuration
**Queue Settings**

You can customize the queue settings in your `config/queue.php` file. For example, you can set the connection and queue name for the Steadfast bulk order processing:

```php
// config/queue.php
'steadfast' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'steadfast',
    'retry_after' => 90,
],
```
**Logging Settings**
You can customize the logging settings in your `config` file. For example, you can set the log channel for the Steadfast activity logs:

```php
'logging' => [
    'enabled' => true,
    'log_level' => 'error', // debug, info, error
],
```

**Exception Handling**

```php
try {
    Steadfast::createOrder(...);
} catch (SteadfastException $e) {
    // Handle error
    $errorContext = $e->getContext();
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## Credits

- [Sabit Ahmad](https://github.com/SabitAhmad)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
