# Laravel SteadFast Courier Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sabitahmad/laravel-steadfast.svg?style=flat-square)](https://packagist.org/packages/sabitahmad/laravel-steadfast)
[![Total Downloads](https://img.shields.io/packagist/dt/sabitahmad/laravel-steadfast.svg?style=flat-square)](https://packagist.org/packages/sabitahmad/laravel-steadfast)

Laravel integration for the SteadFast Courier API with typed DTOs for orders, tracking, returns, balance checks, optional bulk processing, bulk lifecycle events, caching, and database-backed request logging.

## Features

- Create single and bulk orders
- Track shipments by consignment ID, invoice, or tracking code
- Create and fetch return requests
- Check account balance
- Typed request and response DTOs
- Queue-backed bulk processing
- Bulk lifecycle events
- Configurable caching for balance and status lookups
- Optional fraud checking through the SteadFast merchant panel
- Optional request logging and usage statistics
- Built-in artisan commands for testing, statistics, and cleanup

## Installation

```bash
composer require sabitahmad/laravel-steadfast
php artisan steadfast:install
```

Add your credentials to `.env`:

```env
STEADFAST_API_KEY=your_api_key_here
STEADFAST_SECRET_KEY=your_secret_key_here
```

Optional settings:

```env
STEADFAST_BASE_URL=https://portal.packzy.com/api/v1
STEADFAST_TIMEOUT=30
STEADFAST_CONNECT_TIMEOUT=10

STEADFAST_BULK_QUEUE=true
STEADFAST_BULK_CHUNK_SIZE=500
STEADFAST_QUEUE_NAME=default
STEADFAST_QUEUE_CONNECTION=
STEADFAST_BULK_MAX_ATTEMPTS=3
STEADFAST_BULK_BACKOFF=60

STEADFAST_RETRY_TIMES=3
STEADFAST_RETRY_SLEEP=1000

STEADFAST_CACHE_ENABLED=false
STEADFAST_CACHE_TTL=300
STEADFAST_CACHE_PREFIX=steadfast
STEADFAST_CACHE_STORE=

STEADFAST_LOGGING=true
STEADFAST_LOG_REQUESTS=false
STEADFAST_LOG_RESPONSES=true
STEADFAST_CLEANUP_LOGS=true
STEADFAST_KEEP_LOGS_DAYS=30

STEADFAST_FRAUD_CHECKER_ENABLED=false
STEADFAST_FRAUD_CHECKER_EMAIL=your-merchant-email@example.com
STEADFAST_FRAUD_CHECKER_PASSWORD=your-merchant-password
```

Verify the setup:

```bash
php artisan steadfast:test
```

## Usage

### Create an Order

```php
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\SteadFast;

$steadfast = new SteadFast();

$order = new OrderRequest(
    invoice: 'INV-2025-001',
    recipient_name: 'John Doe',
    recipient_phone: '01712345678',
    recipient_address: 'House 1, Road 2, Dhanmondi, Dhaka-1209',
    cod_amount: 1500.00,
    note: 'Handle with care'
);

$response = $steadfast->createOrder($order);

echo $response->getTrackingCode();
```

You can also use optional order fields when needed:

```php
$order = new OrderRequest(
    invoice: 'INV-2025-002',
    recipient_name: 'Jane Doe',
    recipient_phone: '01812345678',
    recipient_address: 'Banani, Dhaka',
    cod_amount: 2200,
    alternative_phone: '01912345678',
    recipient_email: 'jane@example.com',
    item_description: 'Skin care products',
    total_lot: 2,
    delivery_type: 0
);
```

### Use the Facade

```php
use SabitAhmad\SteadFast\Facades\SteadFast;

$balance = SteadFast::getBalance();
```

### Bulk Orders

```php
$orders = [
    new OrderRequest('INV-001', 'John Doe', '01712345678', 'Address 1', 1000),
    new OrderRequest('INV-002', 'Jane Smith', '01787654321', 'Address 2', 1500),
    OrderRequest::fromArray([
        'invoice' => 'INV-003',
        'recipient_name' => 'Bob Wilson',
        'recipient_phone' => '01611111111',
        'recipient_address' => 'Address 3',
        'cod_amount' => 2000,
        'delivery_type' => 1,
    ]),
];

$queued = $steadfast->bulkCreate($orders, true);
$processed = $steadfast->bulkCreate($orders, false);
```

Bulk responses include helper methods:

```php
echo $processed->getSuccessRate();

if ($processed->hasErrors()) {
    $failedOrders = $processed->getFailedOrders();
}
```

### Track an Order

```php
$status = $steadfast->checkStatusByTrackingCode('ABC123XYZ');

if ($status->isDelivered()) {
    echo 'Delivered';
}

echo $status->getStatusDescription();
```

You can also track by invoice or consignment ID:

```php
$steadfast->checkStatusByInvoice('INV-001');
$steadfast->checkStatusByConsignmentId(12345);
```

### Return Requests

```php
use SabitAhmad\SteadFast\DTO\ReturnRequest;

$request = ReturnRequest::byInvoice('INV-001', 'Customer requested return');

$response = $steadfast->createReturnRequest($request);
$single = $steadfast->getReturnRequest(123);
$all = $steadfast->getReturnRequests();
```

You can also create return requests by consignment ID or tracking code:

```php
ReturnRequest::byConsignmentId(12345, 'Damaged item');
ReturnRequest::byTrackingCode('ABC123', 'Wrong item delivered');
```

### Balance

```php
$balance = $steadfast->getBalance();

echo $balance->getFormattedBalance();
```

## Response Helpers

The DTOs expose helper methods so your application code stays clean:

```php
$orderResponse->getConsignmentId();
$orderResponse->getTrackingCode();
$orderResponse->getInvoice();

$status->isPending();
$status->isCancelled();
$status->isOnHold();

$returnResponse->isPending();
$returnResponse->isCompleted();
```

### Fraud Check

This feature logs into the SteadFast merchant panel and is slower than the normal API calls. Enable it only if you need it.

```php
try {
    $fraud = $steadfast->checkFraud('01712345678');

    echo $fraud->success;
    echo $fraud->cancel;
    echo $fraud->getRiskLevel();
} catch (\SabitAhmad\SteadFast\Exceptions\SteadfastException $e) {
    report($e);
}
```

Accepted phone formats are normalized automatically, including `01712345678`, `8801712345678`, `+8801712345678`, and values with spaces or dashes.

Fraud responses also include helper methods:

```php
$fraud->getSuccessRate();
$fraud->getCancelRate();
$fraud->isRisky();
$fraud->getRiskDescription();
```

## Events

Bulk processing dispatches these events:

- `SabitAhmad\SteadFast\Events\BulkOrderStarted`
- `SabitAhmad\SteadFast\Events\BulkOrderCompleted`
- `SabitAhmad\SteadFast\Events\BulkOrderFailed`

Example listener registration:

```php
use Illuminate\Support\Facades\Event;
use SabitAhmad\SteadFast\Events\BulkOrderCompleted;
use SabitAhmad\SteadFast\Events\BulkOrderFailed;
use SabitAhmad\SteadFast\Events\BulkOrderStarted;

Event::listen(BulkOrderStarted::class, function (BulkOrderStarted $event) {
    logger()->info('Bulk order processing started', [
        'order_count' => count($event->orders),
        'unique_id' => $event->uniqueId,
    ]);
});

Event::listen(BulkOrderCompleted::class, function (BulkOrderCompleted $event) {
    logger()->info('Bulk order processing completed', [
        'success_count' => $event->response->success_count,
        'error_count' => $event->response->error_count,
        'unique_id' => $event->uniqueId,
    ]);
});

Event::listen(BulkOrderFailed::class, function (BulkOrderFailed $event) {
    logger()->error('Bulk order processing failed', [
        'error' => $event->exception->getMessage(),
        'unique_id' => $event->uniqueId,
    ]);
});
```

## Caching

Balance and status lookups can be cached:

```env
STEADFAST_CACHE_ENABLED=true
STEADFAST_CACHE_TTL=300
STEADFAST_CACHE_PREFIX=steadfast
STEADFAST_CACHE_STORE=redis
```

Clear cached entries when needed:

```php
$steadfast->clearCache('balance');
$steadfast->clearCache();
```

## Error Handling

```php
use SabitAhmad\SteadFast\Exceptions\SteadfastException;

try {
    $steadfast->createOrder($order);
} catch (SteadfastException $e) {
    match ($e->getCode()) {
        401 => report('Invalid credentials'),
        422 => report($e->getContext()['validation_errors'] ?? []),
        429 => report('Rate limited'),
        503 => report('Service unavailable'),
        default => report($e->getMessage()),
    };
}
```

## Logging and Statistics

If logging is enabled, requests are stored in the `steadfast_logs` table.

Useful scopes on the `SteadfastLog` model:

```php
use SabitAhmad\SteadFast\Models\SteadfastLog;

$recentErrors = SteadfastLog::errors()->recent(24)->get();
$successful = SteadfastLog::successful()->recent(24)->get();
$bulkOperations = SteadfastLog::bulkOperations()->get();
$stats = SteadfastLog::getStats(24);
```

## Artisan Commands

Available commands:

```bash
php artisan steadfast:test
php artisan steadfast:stats
php artisan steadfast:stats --hours=168
php artisan steadfast:cleanup
php artisan steadfast:cleanup --force
```

What they do:

- `steadfast:test`: validates config and checks API connectivity
- `steadfast:stats`: shows request volume, success rate, bulk usage, and top endpoints
- `steadfast:cleanup`: removes old log records from `steadfast_logs`

## API Coverage

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/create_order` | `POST` | Create a single order |
| `/create_order/bulk-order` | `POST` | Create bulk orders |
| `/status_by_cid/{id}` | `GET` | Check status by consignment ID |
| `/status_by_invoice/{invoice}` | `GET` | Check status by invoice |
| `/status_by_trackingcode/{code}` | `GET` | Check status by tracking code |
| `/get_balance` | `GET` | Get account balance |
| `/create_return_request` | `POST` | Create a return request |
| `/get_return_request/{id}` | `GET` | Get one return request |
| `/get_return_requests` | `GET` | Get all return requests |
