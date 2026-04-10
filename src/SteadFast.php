<?php

namespace SabitAhmad\SteadFast;

use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SabitAhmad\SteadFast\DTO\BalanceResponse;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;
use SabitAhmad\SteadFast\DTO\FraudCheckResponse;
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\DTO\OrderResponse;
use SabitAhmad\SteadFast\DTO\ReturnRequest;
use SabitAhmad\SteadFast\DTO\ReturnResponse;
use SabitAhmad\SteadFast\DTO\StatusResponse;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use SabitAhmad\SteadFast\Jobs\ProcessBulkOrders;
use SabitAhmad\SteadFast\Services\SteadfastFraudChecker;
use SabitAhmad\SteadFast\Services\SteadfastHttpClient;
use SabitAhmad\SteadFast\Services\SteadfastLogger;

class SteadFast
{
    protected array $config;

    protected SteadfastHttpClient $httpClient;

    protected CacheRepository $cache;

    protected SteadfastLogger $logger;

    protected SteadfastFraudChecker $fraudChecker;

    /**
     * @throws SteadfastException
     */
    public function __construct(
        ?SteadfastHttpClient $httpClient = null,
        ?SteadfastLogger $logger = null,
        ?SteadfastFraudChecker $fraudChecker = null
    ) {
        $this->config = config('steadfast');
        $this->validateConfig();
        $this->cache = $this->resolveCacheStore();
        $this->logger = $logger ?? app(SteadfastLogger::class);
        $this->httpClient = $httpClient ?? app(SteadfastHttpClient::class);
        $this->fraudChecker = $fraudChecker ?? app(SteadfastFraudChecker::class);
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

    private function resolveCacheStore(): CacheRepository
    {
        $store = $this->config['cache']['store'] ?? null;

        return $store
            ? Cache::store($store)
            : Cache::store();
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

            $response = $this->httpClient->post('/create_order', $order->toArray(), 'single_order');

            return OrderResponse::fromArray($this->httpClient->validateResponse($response));
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

        if ($this->config['cache']['enabled'] && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);

            return BalanceResponse::fromArray($cached);
        }

        try {
            $response = $this->httpClient->get('/get_balance', 'balance_check');

            $processedResponse = $this->httpClient->validateResponse($response);

            if ($this->config['cache']['enabled']) {
                $this->cache->put($cacheKey, $processedResponse, $this->config['cache']['ttl']);
                $this->trackCacheKey($cacheKey);
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

        if ($this->config['cache']['enabled'] && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);

            return StatusResponse::fromArray($cached);
        }

        try {
            $response = $this->httpClient->get($endpoints[$type].$identifier, 'status_check', [
                'identifier' => $identifier,
                'type' => $type,
            ]);

            $processedResponse = $this->httpClient->validateResponse($response);

            if ($this->config['cache']['enabled']) {
                // Cache status for shorter time as it changes frequently
                $this->cache->put($cacheKey, $processedResponse, min($this->config['cache']['ttl'], 60));
                $this->trackCacheKey($cacheKey);
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
            $response = $this->httpClient->post('/create_return_request', $returnRequest->toArray(), 'return_request');

            return ReturnResponse::fromArray($this->httpClient->validateResponse($response));
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
            $response = $this->httpClient->get("/get_return_request/{$id}", 'get_return_request', ['id' => $id]);

            return ReturnResponse::fromArray($this->httpClient->validateResponse($response));
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
            $response = $this->httpClient->get('/get_return_requests', 'get_return_requests');

            $processedResponse = $this->httpClient->validateResponse($response);

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

        $this->logger->log($logData);

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

        if (! empty($errors) && $this->config['logging']['enabled']) {
            $this->logger->log([
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

        $this->logger->log([
            'type' => 'bulk_job_dispatched',
            'request' => ['order_count' => count($orders)],
            'response' => ['queued' => true],
            'endpoint' => 'internal',
            'status_code' => 200,
        ]);

        return new BulkOrderResponse([
            'status' => 'queued',
            'message' => 'Bulk orders processing has been queued for background processing',
            'order_count' => count($orders),
        ]);
    }

    public function processBulkOrders(array $orders): BulkOrderResponse
    {
        $chunks = array_chunk($orders, $this->config['bulk']['chunk_size']);
        $allResponses = [];
        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                // Convert OrderRequest objects to arrays for API
                $orderData = array_map(function ($order) {
                    return $order instanceof OrderRequest ? $order->toArray() : $order;
                }, $chunk);

                $response = $this->httpClient->post(
                    '/create_order/bulk-order',
                    ['data' => json_encode($orderData)],
                    'bulk_order'
                );

                $processedResponse = $this->handleBulkResponse($response);
                $allResponses = array_merge($allResponses, $processedResponse['data']);
            } catch (Exception $e) {
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
        $processed = $this->httpClient->validateResponse($response);

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
            $cacheKey = $this->getCacheKey($key);
            $forgotten = $this->cache->forget($cacheKey);
            $this->untrackCacheKey($cacheKey);

            return $forgotten;
        }

        $cleared = false;

        foreach ($this->getTrackedCacheKeys() as $trackedKey) {
            $cleared = $this->cache->forget($trackedKey) || $cleared;
        }

        $this->cache->forget($this->getCacheKey('_tracked_keys'));

        return $cleared;
    }

    /**
     * Get API configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    private function getTrackedCacheKeys(): array
    {
        return $this->cache->get($this->getCacheKey('_tracked_keys'), []);
    }

    private function trackCacheKey(string $cacheKey): void
    {
        $trackedKeys = $this->getTrackedCacheKeys();

        if (in_array($cacheKey, $trackedKeys, true)) {
            return;
        }

        $trackedKeys[] = $cacheKey;
        $this->cache->forever($this->getCacheKey('_tracked_keys'), $trackedKeys);
    }

    private function untrackCacheKey(string $cacheKey): void
    {
        $trackedKeys = array_values(array_filter(
            $this->getTrackedCacheKeys(),
            fn (string $trackedKey): bool => $trackedKey !== $cacheKey
        ));

        if ($trackedKeys === []) {
            $this->cache->forget($this->getCacheKey('_tracked_keys'));

            return;
        }

        $this->cache->forever($this->getCacheKey('_tracked_keys'), $trackedKeys);
    }

    public function checkFraud(string $phoneNumber): FraudCheckResponse
    {
        return $this->fraudChecker->check($phoneNumber);
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
