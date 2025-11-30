# Laravel SteadFast Courier Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sabitahmad/laravel-steadfast.svg?style=flat-square)](https://packagist.org/packages/sabitahmad/laravel-steadfast)
[![Total Downloads](https://img.shields.io/packagist/dt/sabitahmad/laravel-steadfast.svg?style=flat-square)](https://packagist.org/packages/sabitahmad/laravel-steadfast)

A comprehensive Laravel package for integrating with SteadFast Courier API. This package provides a clean, robust interface for managing orders, tracking shipments, and handling return requests with advanced features like bulk processing, caching, detailed logging, and event-driven architecture.

## Features

- **Complete API Coverage**: All SteadFast API endpoints implemented
- **Individual & Bulk Orders**: Efficient single and batch order processing
- **Return Requests**: Create and manage return requests
- **Status Tracking**: Real-time shipment status tracking
- **Balance Management**: Account balance verification
- **Fraud Checker**: Customer reliability analysis via order history (NEW)
- **Queue Support**: Background processing for bulk operations
- **Comprehensive Validation**: Input validation with custom rules
- **Advanced Error Handling**: Specific exceptions for different error types
- **Intelligent Caching**: Optional response caching for better performance
- **Detailed Logging**: Request/response logging with statistics
- **Event System**: Events for bulk operations monitoring
- **Artisan Commands**: Built-in commands for testing and maintenance
- **Retry Logic**: Automatic retry with exponential backoff
- **Data Security**: Sensitive data filtering in logs


## Installation

Install the package via Composer:

```bash
composer require sabitahmad/laravel-steadfast
```

Install and configure the package:

```bash
php artisan steadfast:install
```

This will:
- Publish the configuration file
- Run the database migrations
- Guide you through the setup process

## Configuration

Add your SteadFast API credentials to your `.env` file:

```env
# Required
STEADFAST_API_KEY=your_api_key_here
STEADFAST_SECRET_KEY=your_secret_key_here

# Optional - API Configuration
STEADFAST_BASE_URL=https://portal.packzy.com/api/v1
STEADFAST_TIMEOUT=30
STEADFAST_CONNECT_TIMEOUT=10

# Optional - Bulk Processing
STEADFAST_BULK_QUEUE=true
STEADFAST_BULK_CHUNK_SIZE=500
STEADFAST_QUEUE_NAME=default
STEADFAST_BULK_MAX_ATTEMPTS=3
STEADFAST_BULK_BACKOFF=60

# Optional - Retry Configuration
STEADFAST_RETRY_TIMES=3
STEADFAST_RETRY_SLEEP=1000

# Optional - Caching
STEADFAST_CACHE_ENABLED=false
STEADFAST_CACHE_TTL=300
STEADFAST_CACHE_PREFIX=steadfast

# Optional - Logging
STEADFAST_LOGGING=true
STEADFAST_LOG_REQUESTS=false
STEADFAST_LOG_RESPONSES=true
STEADFAST_CLEANUP_LOGS=true
STEADFAST_KEEP_LOGS_DAYS=30

# Optional - Validation
STEADFAST_STRICT_PHONE=true
STEADFAST_REQUIRE_EMAIL=false

# Optional - Fraud Checker (NEW)
STEADFAST_FRAUD_CHECKER_ENABLED=false
STEADFAST_FRAUD_CHECKER_EMAIL=your-merchant-email@example.com
STEADFAST_FRAUD_CHECKER_PASSWORD=your-merchant-password
STEADFAST_FRAUD_VERY_HIGH_THRESHOLD=75
STEADFAST_FRAUD_HIGH_THRESHOLD=50
STEADFAST_FRAUD_MEDIUM_THRESHOLD=25
STEADFAST_FRAUD_CACHE_ENABLED=true
STEADFAST_FRAUD_CACHE_TTL=3600
```

Test your configuration:

```bash
php artisan steadfast:test
```

## Usage

### Basic Order Creation

```php
use SabitAhmad\SteadFast\SteadFast;
use SabitAhmad\SteadFast\DTO\OrderRequest;

$steadfast = new SteadFast();

// Basic order
$order = new OrderRequest(
    invoice: 'INV-2025-001',
    recipient_name: 'John Doe',
    recipient_phone: '01712345678',
    recipient_address: 'House 1, Road 2, Dhanmondi, Dhaka-1209',
    cod_amount: 1500.00,
    note: 'Handle with care'
);

$response = $steadfast->createOrder($order);
echo "Order created! Tracking: " . $response->getTrackingCode();
```

### Advanced Order Creation

```php
// Order with all optional fields
$order = new OrderRequest(
    invoice: 'INV-2025-001',
    recipient_name: 'John Doe',
    recipient_phone: '01712345678',
    recipient_address: 'House 1, Road 2, Dhanmondi, Dhaka-1209',
    cod_amount: 1500.00,
    alternative_phone: '01987654321',        // NEW: Alternative phone
    recipient_email: 'john@example.com',     // NEW: Email address
    note: 'Deliver between 10 AM - 2 PM',
    item_description: 'Electronics items',   // NEW: Item description
    total_lot: 2,                           // NEW: Total items
    delivery_type: 0                        // NEW: 0=home, 1=point delivery
);

$response = $steadfast->createOrder($order);
```

### Using Facade

```php
use SabitAhmad\SteadFast\Facades\SteadFast;

// Check balance
$balance = SteadFast::getBalance();
echo $balance->getFormattedBalance(); // "1,500.00 BDT"

// Create order
$response = SteadFast::createOrder($order);
```

### Bulk Order Processing

```php
// Create multiple orders
$orders = [
    new OrderRequest('INV-001', 'John Doe', '01712345678', 'Address 1', 1000),
    new OrderRequest('INV-002', 'Jane Smith', '01787654321', 'Address 2', 1500),
    OrderRequest::fromArray([
        'invoice' => 'INV-003',
        'recipient_name' => 'Bob Wilson',
        'recipient_phone' => '01611111111',
        'recipient_address' => 'Address 3',
        'cod_amount' => 2000,
        'delivery_type' => 1, // Point delivery
    ]),
];

// Process in background queue (recommended for large batches)
$response = $steadfast->bulkCreate($orders, true);
echo "Queued {$response->order_count} orders for processing";

// Process immediately (for small batches)
$response = $steadfast->bulkCreate($orders, false);
echo "Success rate: {$response->getSuccessRate()}%";
echo "Successful orders: {$response->success_count}";
echo "Failed orders: {$response->error_count}";
```

### Order Status Tracking

```php
// Multiple ways to track orders
$status = $steadfast->checkStatusByTrackingCode('ABC123XYZ');
$status = $steadfast->checkStatusByInvoice('INV-001');
$status = $steadfast->checkStatusByConsignmentId(12345);

// Rich status information
echo "Status: " . $status->delivery_status;
echo "Description: " . $status->getStatusDescription();

// Status checking methods
if ($status->isDelivered()) {
    echo "Order delivered successfully!";
} elseif ($status->isCancelled()) {
    echo "Order was cancelled";
} elseif ($status->isPending()) {
    echo "Order is still being processed";
} elseif ($status->isOnHold()) {
    echo "Order is on hold";
}
```

### Return Requests (NEW Feature)

```php
use SabitAhmad\SteadFast\DTO\ReturnRequest;

// Create return request by invoice
$returnRequest = ReturnRequest::byInvoice('INV-001', 'Customer requested return');
$response = $steadfast->createReturnRequest($returnRequest);

// Create return request by consignment ID
$returnRequest = ReturnRequest::byConsignmentId(12345, 'Damaged item');
$response = $steadfast->createReturnRequest($returnRequest);

// Create return request by tracking code
$returnRequest = ReturnRequest::byTrackingCode('ABC123', 'Wrong item delivered');
$response = $steadfast->createReturnRequest($returnRequest);

// Get single return request
$returnRequest = $steadfast->getReturnRequest(123);
echo "Status: " . $returnRequest->getStatusDescription();

// Check return status
if ($returnRequest->isPending()) {
    echo "Return request is pending approval";
} elseif ($returnRequest->isCompleted()) {
    echo "Return has been completed";
}

// Get all return requests
$returnRequests = $steadfast->getReturnRequests();
foreach ($returnRequests as $request) {
    echo "Return ID: {$request->id}, Status: {$request->status}";
}
```

### Account Balance

```php
$balance = $steadfast->getBalance();

echo "Current Balance: " . $balance->getFormattedBalance();
echo "Raw Amount: " . $balance->current_balance;

if ($balance->isSuccessful()) {
    echo "Balance retrieved successfully";
}
```

### Fraud Checker (NEW Feature)

Check customer reliability by analyzing their order history. This feature uses web scraping to access the Steadfast merchant panel and retrieve fraud statistics.

#### Configuration

First, enable and configure fraud checking in your `.env`:

```env
# Enable fraud checker
STEADFAST_FRAUD_CHECKER_ENABLED=true
STEADFAST_FRAUD_CHECKER_EMAIL=your-merchant-email@example.com
STEADFAST_FRAUD_CHECKER_PASSWORD=your-merchant-password

# Optional: Configure risk thresholds (percentages)
STEADFAST_FRAUD_VERY_HIGH_THRESHOLD=75
STEADFAST_FRAUD_HIGH_THRESHOLD=50
STEADFAST_FRAUD_MEDIUM_THRESHOLD=25

# Optional: Enable caching for fraud checks
STEADFAST_FRAUD_CACHE_ENABLED=true
STEADFAST_FRAUD_CACHE_TTL=3600
```

#### Basic Usage

```php
use SabitAhmad\SteadFast\SteadFast;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;

$steadfast = new SteadFast();

try {
    // Check fraud status by phone number
    $fraudCheck = $steadfast->checkFraud('01712345678');
    
    // Get order statistics
    echo "Successful Orders: " . $fraudCheck->success;
    echo "Cancelled Orders: " . $fraudCheck->cancel;
    echo "Total Orders: " . $fraudCheck->total;
    
    // Get calculated rates
    echo "Success Rate: " . $fraudCheck->getSuccessRate() . "%";
    echo "Cancel Rate: " . $fraudCheck->getCancelRate() . "%";
    
    // Get risk assessment
    echo "Risk Level: " . $fraudCheck->getRiskLevel();        // 'none', 'low', 'medium', 'high', 'very_high'
    echo "Risk Description: " . $fraudCheck->getRiskDescription();
    
} catch (SteadfastException $e) {
    echo "Error: " . $e->getMessage();
}
```

#### Advanced Usage

```php
// Check if customer is risky (default threshold: 50% cancellation rate)
if ($fraudCheck->isRisky()) {
    echo "High-risk customer detected!";
    // Require prepayment or decline order
}

// Use custom threshold
if ($fraudCheck->isRisky(threshold: 30)) {
    echo "Customer exceeds 30% cancellation rate";
}

// Make decisions based on risk level
match ($fraudCheck->getRiskLevel()) {
    'very_high' => $this->declineOrder($order),
    'high' => $this->requirePrepayment($order),
    'medium' => $this->assignToExperiencedCourier($order),
    'low' => $this->processNormally($order),
    'none' => $this->processWithPriority($order),
};

// Check if fraud check was successful
if ($fraudCheck->isSuccessful()) {
    echo "Fraud check completed successfully";
} else {
    echo "Error: " . $fraudCheck->error;
}
```

#### Real-World Example

```php
public function validateCustomerBeforeOrder(string $phoneNumber, float $orderAmount)
{
    try {
        $fraudCheck = $this->steadfast->checkFraud($phoneNumber);
        
        // New customer (no order history)
        if ($fraudCheck->total === 0) {
            if ($orderAmount > 5000) {
                return [
                    'approved' => false,
                    'reason' => 'New customer with high-value order. Prepayment required.',
                    'require_prepayment' => true,
                ];
            }
            return ['approved' => true, 'note' => 'New customer'];
        }
        
        // Experienced customer with good track record
        if ($fraudCheck->getSuccessRate() >= 80) {
            return [
                'approved' => true,
                'note' => 'Trusted customer',
                'priority_processing' => true,
            ];
        }
        
        // High-risk customer
        if ($fraudCheck->isRisky(threshold: 40)) {
            return [
                'approved' => false,
                'reason' => "High cancellation rate: {$fraudCheck->getCancelRate()}%",
                'customer_stats' => [
                    'success' => $fraudCheck->success,
                    'cancel' => $fraudCheck->cancel,
                    'total' => $fraudCheck->total,
                ],
            ];
        }
        
        // Medium-risk customer
        return [
            'approved' => true,
            'note' => 'Medium risk - monitor closely',
            'send_confirmation' => true,
        ];
        
    } catch (SteadfastException $e) {
        // Log error and continue without fraud check
        Log::warning('Fraud check failed', [
            'phone' => $phoneNumber,
            'error' => $e->getMessage(),
        ]);
        
        return ['approved' => true, 'note' => 'Fraud check unavailable'];
    }
}
```

#### Understanding Risk Levels

The fraud checker automatically categorizes customers into risk levels:

| Risk Level | Cancel Rate | Description | Recommendation |
|-----------|-------------|-------------|----------------|
| **Very High** | ≥ 75% | Customer cancels most orders | Consider declining |
| **High** | 50-74% | High cancellation history | Require prepayment |
| **Medium** | 25-49% | Moderate risk | Monitor closely |
| **Low** | 1-24% | Occasional cancellations | Safe to proceed |
| **None** | 0% | New or perfect record | Trustworthy |

#### Phone Number Formats

The fraud checker automatically normalizes phone numbers. Accepted formats:

```php
// Valid formats (automatically normalized):
$fraudCheck = $steadfast->checkFraud('01712345678');    // Standard format (preferred)
$fraudCheck = $steadfast->checkFraud('8801712345678');  // With country code 88
$fraudCheck = $steadfast->checkFraud('+8801712345678'); // International format
$fraudCheck = $steadfast->checkFraud('017 1234 5678');  // With spaces
$fraudCheck = $steadfast->checkFraud('017-1234-5678');  // With dashes

// Invalid formats (will throw exception):
// '1234567890'        - Too short
// '02171234567'       - Invalid prefix (must start with 01)
// '01212345678'       - Second digit must be 3-9
// '011234567890'      - Too long
```

**Valid Pattern:** Must be 11 digits starting with `01` followed by `3-9`, then 8 more digits.

**Examples of valid phone numbers:**
- `01712345678` (Grameenphone)
- `01812345678` (Robi)
- `01912345678` (Banglalink)
- `01612345678` (Airtel)
- `01512345678` (Teletalk)

#### Error Handling

```php
use SabitAhmad\SteadFast\Exceptions\SteadfastException;

try {
    $fraudCheck = $steadfast->checkFraud($phoneNumber);
} catch (SteadfastException $e) {
    match ($e->getCode()) {
        500 => throw new Exception('Fraud checker not enabled or configured'),
        422 => throw new Exception('Invalid phone number format. Must be 01XXXXXXXXX (e.g., 01712345678)'),
        default => throw new Exception('Fraud check failed: ' . $e->getMessage())
    };
}
```

#### Performance Considerations

1. **Caching**: Enable fraud check caching to avoid repeated web scraping:
   ```env
   STEADFAST_FRAUD_CACHE_ENABLED=true
   STEADFAST_FRAUD_CACHE_TTL=3600  # Cache for 1 hour
   ```

2. **Async Processing**: For high-traffic applications, consider checking fraud status asynchronously:
   ```php
   dispatch(function () use ($phoneNumber) {
       $fraudCheck = app(SteadFast::class)->checkFraud($phoneNumber);
       // Store results in database
       FraudCheck::updateOrCreate(
           ['phone_number' => $phoneNumber],
           $fraudCheck->toArray()
       );
   });
   ```

3. **Rate Limiting**: The fraud checker performs web scraping which is slower than API calls. Use wisely and implement caching.

## Advanced Features

### Event System (NEW)

Listen to bulk order events for monitoring and notifications:

```php
use SabitAhmad\SteadFast\Events\{BulkOrderStarted, BulkOrderCompleted, BulkOrderFailed};

// In your EventServiceProvider
Event::listen(BulkOrderStarted::class, function ($event) {
    Log::info("Bulk order processing started", [
        'order_count' => count($event->orders),
        'unique_id' => $event->uniqueId
    ]);
});

Event::listen(BulkOrderCompleted::class, function ($event) {
    // Send notification, update dashboard, etc.
    Log::info("Bulk order completed", [
        'success_count' => $event->response->success_count,
        'error_count' => $event->response->error_count,
        'success_rate' => $event->response->getSuccessRate()
    ]);
});

Event::listen(BulkOrderFailed::class, function ($event) {
    // Alert administrators, retry logic, etc.
    Log::error("Bulk order failed", [
        'error' => $event->exception->getMessage(),
        'order_count' => count($event->orders)
    ]);
});
```

### Caching (NEW)

Enable intelligent caching for better performance:

```php
// Enable caching in .env
STEADFAST_CACHE_ENABLED=true
STEADFAST_CACHE_TTL=300  // 5 minutes

// Clear cache when needed
$steadfast->clearCache(); // Clear all cache
$steadfast->clearCache('balance'); // Clear specific cache
```

### Advanced Error Handling

```php
use SabitAhmad\SteadFast\Exceptions\SteadfastException;

try {
    $response = $steadfast->createOrder($order);
} catch (SteadfastException $e) {
    match ($e->getCode()) {
        401 => $this->handleAuthenticationError($e), // Invalid credentials
        422 => $this->handleValidationError($e),     // Validation failed
        429 => $this->handleRateLimit($e),           // Rate limit exceeded
        503 => $this->handleServiceDown($e),         // Service unavailable
        default => $this->handleGenericError($e)
    };
}

private function handleValidationError(SteadfastException $e)
{
    $errors = $e->getContext()['validation_errors'] ?? [];
    foreach ($errors as $field => $messages) {
        echo "Field $field: " . implode(', ', $messages);
    }
}

private function handleRateLimit(SteadfastException $e)
{
    $retryAfter = $e->getContext()['retry_after'] ?? 60;
    echo "Rate limit exceeded. Retry after $retryAfter seconds";
}
```

### Queue Configuration

For better queue management, configure dedicated queues:

```php
// config/queue.php
'connections' => [
    'steadfast' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'steadfast-orders',
        'retry_after' => 300,
        'block_for' => 0,
    ],
],

// In your .env
STEADFAST_QUEUE_CONNECTION=steadfast
STEADFAST_QUEUE_NAME=high-priority
```

Run dedicated workers:

```bash
php artisan queue:work steadfast --queue=high-priority,default
```

## Artisan Commands

### Test API Connection

```bash
# Test your configuration
php artisan steadfast:test
```

This command will:
- Validate your API credentials
- Test API connectivity
- Check your current balance
- Verify configuration settings

### View Statistics

```bash
# View last 24 hours (default)
php artisan steadfast:stats

# View last 7 days
php artisan steadfast:stats --hours=168

# View last 30 days
php artisan steadfast:stats --hours=720
```

Sample output:
```
Steadfast API Statistics (Last 24 hours)
==================================================
Metric                | Value
---------------------|----------
Total Requests       | 1,245
Successful           | 1,180
Errors              | 65
Success Rate        | 94.78%
Bulk Operations     | 12
Average Duration    | 245.67ms

Top Endpoints:
Endpoint                    | Requests
---------------------------|----------
/create_order              | 890
/create_order/bulk-order   | 234
/status_by_invoice         | 121
```

### Clean Up Old Logs

```bash
# Interactive cleanup
php artisan steadfast:cleanup

# Force cleanup without confirmation
php artisan steadfast:cleanup --force
```

## Monitoring & Logging

### Database Statistics

Query the logs table for insights:

```php
use SabitAhmad\SteadFast\Models\SteadfastLog;

// Get recent errors
$errors = SteadfastLog::errors()->recent(24)->get();

// Get successful operations
$successful = SteadfastLog::successful()->recent(24)->get();

// Get bulk operations
$bulkOps = SteadfastLog::bulkOperations()->get();

// Get statistics
$stats = SteadfastLog::getStats(24); // Last 24 hours
```

### Log Scopes

The `SteadfastLog` model includes useful query scopes:

```php
// Filter by type
SteadfastLog::ofType('single_order')->get();

// Filter by status code
SteadfastLog::withStatusCode(200)->get();

// Get only errors
SteadfastLog::errors()->get();

// Get only successful requests
SteadfastLog::successful()->get();

// Get recent logs
SteadfastLog::recent(48)->get(); // Last 48 hours

// Get bulk operations
SteadfastLog::bulkOperations()->get();
```

## API Endpoints Coverage

| Endpoint | Method | Description | Status | New Features |
|----------|--------|-------------|--------|--------------|  
| `/create_order` | POST | Create single order | Yes | Enhanced validation, all fields |
| `/create_order/bulk-order` | POST | Create bulk orders | Yes | Queue support, events, chunking |
| `/status_by_cid/{id}` | GET | Check status by consignment ID | Yes | Rich status objects, caching |
| `/status_by_invoice/{invoice}` | GET | Check status by invoice | Yes | Rich status objects, caching |
| `/status_by_trackingcode/{code}` | GET | Check status by tracking code | Yes | Rich status objects, caching |
| `/get_balance` | GET | Get account balance | Yes | Formatted output, caching |
| `/create_return_request` | POST | Create return request | Yes | **NEW** - Full implementation |
| `/get_return_request/{id}` | GET | Get single return request | Yes | **NEW** - Full implementation |
| `/get_return_requests` | GET | Get all return requests | Yes | **NEW** - Full implementation |## Validation Rules

The package includes comprehensive validation:

```php
// Order validation rules
'invoice' => 'required|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
'recipient_name' => 'required|string|max:100',
'recipient_phone' => 'required|string|regex:/^01[0-9]{9}$/',
'alternative_phone' => 'nullable|string|regex:/^01[0-9]{9}$/',
'recipient_email' => 'nullable|email|max:255',
'recipient_address' => 'required|string|max:250',
'cod_amount' => 'required|numeric|min:0',
'note' => 'nullable|string|max:500',
'item_description' => 'nullable|string|max:500',
'total_lot' => 'nullable|integer|min:1',
'delivery_type' => 'nullable|integer|in:0,1',
```

Customize validation in config:

```env
STEADFAST_STRICT_PHONE=true        # Enforce BD phone format
STEADFAST_REQUIRE_EMAIL=false      # Make email required
STEADFAST_MAX_INVOICE_LENGTH=255   # Max invoice length
STEADFAST_MAX_ADDRESS_LENGTH=250   # Max address length
STEADFAST_MAX_NAME_LENGTH=100      # Max name length
```

## Security Features

### Sensitive Data Protection

- API keys automatically filtered from logs
- Configurable request/response logging
- Secure credential handling

### Input Sanitization

- Comprehensive input validation
- SQL injection prevention
- XSS protection in logged data

## Performance Optimizations

### Bulk Processing

- Chunked processing (configurable chunk size)
- Queue-based background processing
- Parallel processing support
- Memory-efficient handling

### Caching

- Response caching for frequently accessed data
- Configurable TTL per endpoint
- Cache invalidation strategies
- Redis/Database cache support

### Connection Management

- Persistent HTTP connections
- Configurable timeouts
- Retry logic with exponential backoff
- Connection pooling

## Error Scenarios & Handling

### Common Issues & Solutions

```php
// Handle specific error scenarios
try {
    $response = $steadfast->createOrder($order);
} catch (SteadfastException $e) {
    if ($e->getCode() === 422) {
        // Validation errors
        $errors = $e->getContext()['validation_errors'];
        return response()->json(['errors' => $errors], 422);
    }
    
    if ($e->getCode() === 401) {
        // Invalid credentials - check API keys
        Log::error('Steadfast authentication failed', [
            'api_key' => substr(config('steadfast.api_key'), 0, 8) . '...'
        ]);
        return response()->json(['error' => 'API authentication failed'], 401);
    }
    
    if ($e->getCode() === 429) {
        // Rate limit - implement backoff strategy
        $retryAfter = $e->getContext()['retry_after'] ?? 60;
        return response()->json([
            'error' => 'Rate limit exceeded',
            'retry_after' => $retryAfter
        ], 429);
    }
}
```

## Migration from v1.x

If upgrading from an older version:

1. **Update configuration:**
```bash
php artisan vendor:publish --tag="laravel-steadfast-config" --force
```

2. **Run new migrations:**
```bash
php artisan migrate
```

3. **Update your code:**
```php
// Old way
$steadfast->bulkCreate($orders);

// New way (same method, but now returns rich response objects)
$response = $steadfast->bulkCreate($orders);
echo $response->getSuccessRate(); // New methods available
```

## Testing

Run the package tests:

```bash
composer test
```

Test your integration:

```bash
php artisan steadfast:test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sabit Ahmad](https://github.com/sabitahmadumid) - Package author and maintainer
- [SteadFast Courier](https://steadfast.com.bd/) - API provider
- [Laravel Community](https://laravel.com/) - Framework and inspiration
- All contributors who help improve this package

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

### Getting Help

1. **Documentation**: Read this README thoroughly
2. **Test Configuration**: Run `php artisan steadfast:test`
3. **Check Logs**: Query the `steadfast_logs` table for API issues
4. **View Statistics**: Run `php artisan steadfast:stats` for insights
5. **GitHub Issues**: [Open an issue](https://github.com/sabitahmadumid/laravel-steadfast/issues) for bugs or feature requests

### Troubleshooting

**API Connection Issues:**
```bash
# Test your configuration
php artisan steadfast:test

# Check recent errors
php artisan steadfast:stats --hours=1
```

**Queue Processing Issues:**
```bash
# Make sure queue worker is running
php artisan queue:work

# Check failed jobs
php artisan queue:failed
```

**Common Configuration Issues:**
- Verify API credentials in `.env`
- Ensure database migrations are run
- Check queue configuration
- Verify PHP version (8.1+ required)

---

**Happy shipping with SteadFast!**
