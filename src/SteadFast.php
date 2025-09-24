<?php

namespace SabitAhmad\SteadFast;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SabitAhmad\SteadFast\DTO\BalanceResponse;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\DTO\OrderResponse;
use SabitAhmad\SteadFast\DTO\ReturnRequest;
use SabitAhmad\SteadFast\DTO\ReturnResponse;
use SabitAhmad\SteadFast\DTO\StatusResponse;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use SabitAhmad\SteadFast\Jobs\ProcessBulkOrders;
use SabitAhmad\SteadFast\Models\SteadfastLog;
use Throwable;

class SteadFast
{
    protected array $config;

    protected PendingRequest $httpClient;

    /**
     * @throws SteadfastException
     */
    public function __construct()
    {
        $this->validateConfig();
        $this->config = config('steadfast');
        $this->initializeHttpClient();
    }

    /**
     * @throws SteadfastException
     */
    private function validateConfig(): void
    {
        $apiKey = config('steadfast.api_key');
        $secretKey = config('steadfast.secret_key');

        if (empty($apiKey)) {
            throw SteadfastException::invalidConfig('API Key');
        }

        if (empty($secretKey)) {
            throw SteadfastException::invalidConfig('Secret Key');
        }

        if (empty(config('steadfast.base_url'))) {
            throw SteadfastException::invalidConfig('Base URL');
        }
    }

    private function initializeHttpClient(): void
    {
        $this->httpClient = Http::baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->connectTimeout($this->config['connect_timeout'] ?? 10)
            ->withHeaders([
                'Api-Key' => $this->config['api_key'],
                'Secret-Key' => $this->config['secret_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-SteadFast-Package/2.0',
            ])
            ->retry(
                $this->config['retry']['times'],
                $this->config['retry']['sleep'],
                function ($exception, $request) {
                    return $this->shouldRetry($exception);
                }
            );
    }

    /**
     * Determine if a request should be retried
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof RequestException) {
            $statusCode = $exception->response?->status();

            return in_array($statusCode, $this->config['retry']['when'] ?? [500, 502, 503, 504, 429]);
        }

        return false;
    }

    /**
     * Create a single order
     *
     * @throws SteadfastException
     */
    public function createOrder(OrderRequest $order): OrderResponse
    {
        try {
            $order->validate();

            $response = $this->makeRequest(
                endpoint: '/create_order',
                data: $order->toArray(),
                type: 'single_order'
            );

            return OrderResponse::fromArray($this->handleResponse($response));
        } catch (Exception $e) {
            $this->handleException($e, [
                'order' => $order->toArray(),
                'endpoint' => '/create_order',
            ]);
        }
    }

    /**
     * Get current account balance
     *
     * @throws SteadfastException
     */
    public function getBalance(): BalanceResponse
    {
        $cacheKey = $this->getCacheKey('balance');

        if ($this->config['cache']['enabled'] && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return BalanceResponse::fromArray($cached);
        }

        try {
            $response = $this->httpClient
                ->get('/get_balance')
                ->throw()
                ->json();

            $this->logRequest([
                'type' => 'balance_check',
                'request' => [],
                'response' => $response,
                'endpoint' => '/get_balance',
                'status_code' => $response['status'] ?? 500,
            ]);

            $processedResponse = $this->handleResponse($response);

            if ($this->config['cache']['enabled']) {
                Cache::put($cacheKey, $processedResponse, $this->config['cache']['ttl']);
            }

            return BalanceResponse::fromArray($processedResponse);
        } catch (Exception $e) {
            $this->handleException($e, ['endpoint' => '/get_balance']);
        }
    }

    /**
     * Check order status by consignment ID
     *
     * @throws SteadfastException
     */
    public function checkStatusByConsignmentId(int $id): StatusResponse
    {
        return $this->checkStatus((string) $id, 'cid');
    }

    /**
     * Check order status by invoice
     *
     * @throws SteadfastException
     */
    public function checkStatusByInvoice(string $invoice): StatusResponse
    {
        return $this->checkStatus($invoice, 'invoice');
    }

    /**
     * Check order status by tracking code
     *
     * @throws SteadfastException
     */
    public function checkStatusByTrackingCode(string $trackingCode): StatusResponse
    {
        return $this->checkStatus($trackingCode, 'tracking');
    }

    /**
     * @throws SteadfastException
     */
    protected function checkStatus(string $identifier, string $type): StatusResponse
    {
        $endpoints = [
            'cid' => '/status_by_cid/',
            'invoice' => '/status_by_invoice/',
            'tracking' => '/status_by_trackingcode/',
        ];

        $cacheKey = $this->getCacheKey("status_{$type}_{$identifier}");

        if ($this->config['cache']['enabled'] && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return StatusResponse::fromArray($cached);
        }

        try {
            $response = $this->httpClient
                ->get($endpoints[$type].$identifier)
                ->throw()
                ->json();

            $this->logRequest([
                'type' => 'status_check',
                'request' => ['identifier' => $identifier, 'type' => $type],
                'response' => $response,
                'endpoint' => $endpoints[$type],
                'status_code' => $response['status'] ?? 500,
            ]);

            $processedResponse = $this->handleResponse($response);

            if ($this->config['cache']['enabled']) {
                // Cache status for shorter time as it changes frequently
                Cache::put($cacheKey, $processedResponse, min($this->config['cache']['ttl'], 60));
            }

            return StatusResponse::fromArray($processedResponse);
        } catch (Exception $e) {
            $this->handleException($e, [
                'identifier' => $identifier,
                'type' => $type,
                'endpoint' => $endpoints[$type] ?? 'unknown',
            ]);
        }
    }

    /**
     * Create a return request
     *
     * @throws SteadfastException
     */
    public function createReturnRequest(ReturnRequest $returnRequest): ReturnResponse
    {
        try {
            $response = $this->makeRequest(
                endpoint: '/create_return_request',
                data: $returnRequest->toArray(),
                type: 'return_request'
            );

            return ReturnResponse::fromArray($this->handleResponse($response));
        } catch (Exception $e) {
            $this->handleException($e, [
                'return_request' => $returnRequest->toArray(),
                'endpoint' => '/create_return_request',
            ]);
        }
    }

    /**
     * Get a single return request by ID
     *
     * @throws SteadfastException
     */
    public function getReturnRequest(int $id): ReturnResponse
    {
        try {
            $response = $this->httpClient
                ->get("/get_return_request/{$id}")
                ->throw()
                ->json();

            $this->logRequest([
                'type' => 'get_return_request',
                'request' => ['id' => $id],
                'response' => $response,
                'endpoint' => '/get_return_request',
                'status_code' => $response['status'] ?? 500,
            ]);

            return ReturnResponse::fromArray($this->handleResponse($response));
        } catch (Exception $e) {
            $this->handleException($e, [
                'id' => $id,
                'endpoint' => '/get_return_request',
            ]);
        }
    }

    /**
     * Get all return requests
     *
     * @throws SteadfastException
     */
    public function getReturnRequests(): array
    {
        try {
            $response = $this->httpClient
                ->get('/get_return_requests')
                ->throw()
                ->json();

            $this->logRequest([
                'type' => 'get_return_requests',
                'request' => [],
                'response' => $response,
                'endpoint' => '/get_return_requests',
                'status_code' => $response['status'] ?? 500,
            ]);

            $processedResponse = $this->handleResponse($response);

            // Convert each return request to ReturnResponse object
            return array_map(
                fn ($item) => ReturnResponse::fromArray($item),
                $processedResponse['data'] ?? $processedResponse
            );
        } catch (Exception $e) {
            $this->handleException($e, ['endpoint' => '/get_return_requests']);
        }
    }

    /**
     * @throws SteadfastException
     */
    protected function handleException(Exception $e, array $context = []): void
    {
        $logData = [
            'type' => 'api_error',
            'request' => $context,
            'response' => ['error' => $e->getMessage()],
            'endpoint' => $context['endpoint'] ?? 'unknown',
            'status_code' => $this->getExceptionStatusCode($e),
            'error' => $e->getMessage(),
            'context' => $context,
        ];

        $this->logRequest($logData);

        if ($e instanceof SteadfastException) {
            // Re-throw with additional context
            throw new SteadfastException(
                $e->getMessage(),
                $e->getCode(),
                $e,
                array_merge($e->getContext(), $context)
            );
        }

        // Handle specific HTTP exceptions
        if ($e instanceof RequestException) {
            $statusCode = $e->response?->status() ?? 500;
            $responseBody = $e->response?->json() ?? [];

            throw match ($statusCode) {
                401 => SteadfastException::authenticationError($responseBody['message'] ?? 'Invalid credentials'),
                404 => SteadfastException::notFoundError($context['resource'] ?? 'Resource', $context['identifier'] ?? 'unknown'),
                429 => SteadfastException::rateLimitExceeded($e->response?->header('Retry-After') ?? 60),
                503 => SteadfastException::serviceUnavailable(),
                default => SteadfastException::apiError($e->getMessage(), $responseBody)
            };
        }

        // Wrap generic exceptions
        throw new SteadfastException(
            'Service Error: '.$e->getMessage(),
            $e->getCode() ?: 500,
            $e,
            $context
        );
    }

    private function getExceptionStatusCode(Exception $e): int
    {
        if ($e instanceof RequestException) {
            return $e->response?->status() ?? 500;
        }

        return $e->getCode() ?: 500;
    }

    /**
     * Create multiple orders in bulk
     *
     * @param  array  $orders  Array of OrderRequest objects or arrays
     * @param  bool|null  $useQueue  Whether to use queue (defaults to config)
     *
     * @throws SteadfastException
     */
    public function bulkCreate(array $orders, ?bool $useQueue = null): BulkOrderResponse
    {
        if (empty($orders)) {
            throw SteadfastException::bulkOrderError('No orders provided for bulk creation');
        }

        if (count($orders) > 500) {
            throw SteadfastException::bulkOrderError('Maximum 500 orders allowed per bulk request');
        }

        $useQueue = $useQueue ?? $this->config['bulk']['queue'];
        $validatedOrders = $this->validateBulkOrders($orders);

        if (empty($validatedOrders)) {
            throw SteadfastException::bulkOrderError('No valid orders found after validation');
        }

        if ($useQueue) {
            return $this->dispatchBulkOrderJob($validatedOrders);
        }

        return $this->processBulkOrders($validatedOrders);
    }

    /**
     * @throws SteadfastException
     */
    private function handleResponse(array $response): array
    {
        if (! isset($response['status'])) {
            throw SteadfastException::apiError('Invalid response format: missing status field', $response);
        }

        if ($response['status'] !== 200) {
            $message = $response['message'] ?? 'Unknown API error';
            throw SteadfastException::apiError($message, $response);
        }

        return $response;
    }

    private function logRequest(array $logData): void
    {
        if (! $this->config['logging']['enabled']) {
            return;
        }

        // Filter sensitive data from logs
        $filteredRequest = $this->filterSensitiveData($logData['request'] ?? []);
        $filteredResponse = $this->config['logging']['log_responses']
            ? $this->filterSensitiveData($logData['response'] ?? [])
            : null;

        try {
            SteadfastLog::create([
                'type' => $logData['type'],
                'request' => $this->config['logging']['log_requests'] ? $filteredRequest : [],
                'response' => $filteredResponse,
                'endpoint' => $logData['endpoint'],
                'status_code' => $logData['status_code'],
                'error' => $logData['error'] ?? null,
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Steadfast logging failed: '.$e->getMessage(), [
                'original_log_data' => $logData,
            ]);
        }
    }

    private function filterSensitiveData(array $data): array
    {
        $sensitive = ['Api-Key', 'Secret-Key', 'api_key', 'secret_key', 'password', 'token'];

        foreach ($sensitive as $key) {
            if (isset($data[$key])) {
                $data[$key] = '***FILTERED***';
            }
        }

        return $data;
    }

    protected function validateBulkOrders(array $orders): array
    {
        $validOrders = [];
        $errors = [];

        foreach ($orders as $index => $order) {
            try {
                // Convert array to OrderRequest if needed
                if (is_array($order)) {
                    $order = OrderRequest::fromArray($order);
                }

                if (! ($order instanceof OrderRequest)) {
                    $errors[] = "Order at index {$index}: Invalid order type";

                    continue;
                }

                $order->validate();
                $validOrders[] = $order;
            } catch (SteadfastException $e) {
                $errors[] = "Order at index {$index}: ".$e->getMessage();
                Log::warning('Invalid order filtered', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'order' => is_array($order) ? $order : ($order instanceof OrderRequest ? $order->toArray() : 'invalid'),
                ]);
            } catch (Exception $e) {
                $errors[] = "Order at index {$index}: ".$e->getMessage();
                Log::warning('Invalid order filtered: '.$e->getMessage());
            }
        }

        if (! empty($errors) && config('steadfast.logging.enabled')) {
            $this->logRequest([
                'type' => 'bulk_validation_errors',
                'request' => ['total_orders' => count($orders), 'valid_orders' => count($validOrders)],
                'response' => ['errors' => $errors],
                'endpoint' => 'internal',
                'status_code' => 422,
            ]);
        }

        return $validOrders;
    }

    protected function dispatchBulkOrderJob(array $orders): BulkOrderResponse
    {
        $job = ProcessBulkOrders::dispatch($orders)
            ->onQueue($this->config['bulk']['queue_name']);

        // Set connection if specified
        if (! empty($this->config['bulk']['queue_connection'])) {
            $job->onConnection($this->config['bulk']['queue_connection']);
        }

        $this->logRequest([
            'type' => 'bulk_job_dispatched',
            'request' => ['order_count' => count($orders)],
            'response' => ['job_id' => $job->getJobId()],
            'endpoint' => 'internal',
            'status_code' => 200,
        ]);

        return new BulkOrderResponse([
            'status' => 'queued',
            'message' => 'Bulk orders processing has been queued for background processing',
            'order_count' => count($orders),
            'job_id' => $job->getJobId(),
        ]);
    }

    public function processBulkOrders(array $orders): BulkOrderResponse
    {
        $chunks = array_chunk($orders, $this->config['bulk']['chunk_size']);
        $allResponses = [];
        $errors = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                // Convert OrderRequest objects to arrays for API
                $orderData = array_map(function ($order) {
                    return $order instanceof OrderRequest ? $order->toArray() : $order;
                }, $chunk);

                $response = $this->makeRequest(
                    endpoint: '/create_order/bulk-order',
                    data: ['data' => json_encode($orderData)], // API expects JSON encoded data
                    type: 'bulk_order'
                );

                $processedResponse = $this->handleBulkResponse($response);
                $allResponses = array_merge($allResponses, $processedResponse['data']);
            } catch (Exception $e) {
                $errors[] = [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ];

                // Add error entries for each order in failed chunk
                foreach ($chunk as $order) {
                    $allResponses[] = [
                        'invoice' => $order instanceof OrderRequest ? $order->invoice : ($order['invoice'] ?? 'unknown'),
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'consignment_id' => null,
                        'tracking_code' => null,
                    ];
                }

                Log::error('Bulk order chunk failed', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return BulkOrderResponse::fromApiResponse($allResponses);
    }

    protected function makeRequest(string $endpoint, array $data, string $type): array
    {
        $startTime = microtime(true);

        try {
            $response = $this->httpClient
                ->post($endpoint, $data)
                ->throw()
                ->json();

            $duration = microtime(true) - $startTime;

            $this->logRequest([
                'type' => $type,
                'request' => $data,
                'response' => $response,
                'endpoint' => $endpoint,
                'status_code' => $response['status'] ?? 500,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $response;
        } catch (RequestException $e) {
            $duration = microtime(true) - $startTime;
            $responseBody = $e->response?->json() ?? [];

            $this->logRequest([
                'type' => $type.'_error',
                'request' => $data,
                'response' => $responseBody,
                'endpoint' => $endpoint,
                'status_code' => $e->response?->status() ?? 500,
                'duration_ms' => round($duration * 1000, 2),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @throws SteadfastException
     */
    protected function handleBulkResponse(array $response): array
    {
        // For bulk orders, the API returns an array directly, not wrapped in status/data
        if (isset($response[0]) && is_array($response[0])) {
            return [
                'status' => 'success',
                'data' => $response,
                'processed_at' => now()->toDateTimeString(),
            ];
        }

        // Fallback for wrapped responses
        $processed = $this->handleResponse($response);

        return [
            'status' => 'success',
            'data' => $processed['data'] ?? $processed,
            'processed_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get cache key for caching
     */
    private function getCacheKey(string $key): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'steadfast';

        return "{$prefix}:{$key}";
    }

    /**
     * Clear cached data
     */
    public function clearCache(?string $key = null): bool
    {
        if (! $this->config['cache']['enabled']) {
            return false;
        }

        if ($key) {
            return Cache::forget($this->getCacheKey($key));
        }

        // Clear all steadfast cache
        $prefix = $this->config['cache']['prefix'] ?? 'steadfast';

        return Cache::flush(); // Note: This flushes all cache, might want to implement prefix-based clearing
    }

    /**
     * Get API configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Test API connection
     *
     * @throws SteadfastException
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->getBalance();

            return [
                'status' => 'success',
                'message' => 'API connection successful',
                'balance' => $balance->current_balance,
                'timestamp' => now()->toDateTimeString(),
            ];
        } catch (Exception $e) {
            throw SteadfastException::connectionError($e);
        }
    }
}
