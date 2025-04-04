<?php

namespace SabitAhmad\SteadFast;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use SabitAhmad\SteadFast\Jobs\ProcessBulkOrders;
use SabitAhmad\SteadFast\Models\SteadfastLog;

class SteadFast
{
    protected array $config;

    protected mixed $httpClient;

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
        if (empty(config('steadfast.api_key')) || empty(config('steadfast.secret_key'))) {
            throw SteadfastException::invalidConfig('API keys');
        }
    }

    private function initializeHttpClient(): void
    {
        $this->httpClient = Http::baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->withHeaders([
                'Api-Key' => $this->config['api_key'],
                'Secret-Key' => $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ]);
    }

    public function createOrder(OrderRequest $order): array
    {
        try {
            $order->validate();

            $response = $this->makeRequest(
                endpoint: '/create_order',
                data: $order->toArray(),
                type: 'single_order'
            );

            return $this->handleResponse($response);
        } catch (Exception $e) {
            $this->handleException($e, [
                'order' => $order->toArray(),
                'endpoint' => '/create_order',
            ]);
        }
    }

    public function getBalance(): array
    {
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

            return $this->handleResponse($response);
        } catch (Exception $e) {
            $this->handleException($e, ['endpoint' => '/get_balance']);
        }
    }

    public function checkStatusByConsignmentId(int $id): array
    {
        return $this->checkStatus($id, 'cid');
    }

    public function checkStatusByInvoice(string $invoice): array
    {
        return $this->checkStatus($invoice, 'invoice');
    }

    public function checkStatusByTrackingCode(string $trackingCode): array
    {
        return $this->checkStatus($trackingCode, 'tracking');
    }

    protected function checkStatus(string $identifier, string $type): array
    {
        $endpoints = [
            'cid' => '/status_by_cid/',
            'invoice' => '/status_by_invoice/',
            'tracking' => '/status_by_trackingcode/',
        ];

        try {
            $response = $this->httpClient
                ->get($endpoints[$type].$identifier)
                ->throw()
                ->json();

            $this->logRequest([
                'type' => 'status_check',
                'request' => ['identifier' => $identifier],
                'response' => $response,
                'endpoint' => $endpoints[$type],
                'status_code' => $response['status'] ?? 500,
            ]);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            $this->handleException($e, [
                'identifier' => $identifier,
                'type' => $type,
            ]);
        }
    }

    /**
     * @throws SteadfastException
     */
    protected function handleException(\Exception $e, array $context = []): void
    {
        $logData = [
            'type' => 'api_error',
            'request' => $context,
            'response' => ['error' => $e->getMessage()],
            'endpoint' => $context['endpoint'] ?? 'unknown',
            'status_code' => $e->getCode() ?: 500,
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

        // Wrap generic exceptions
        throw new SteadfastException(
            'Service Error: '.$e->getMessage(),
            $e->getCode(),
            $e,
            $context
        );
    }

    public function bulkCreate(array $orders, ?bool $useQueue = null): BulkOrderResponse
    {
        $useQueue = $useQueue ?? $this->config['bulk']['queue'];
        $validatedOrders = $this->validateBulkOrders($orders);

        if ($useQueue) {
            return $this->dispatchBulkOrderJob($validatedOrders);
        }

        return $this->processBulkOrders($validatedOrders);
    }

    /**
     * @throws SteadfastException
     */
    private function handleResponse($response)
    {

        if (! isset($response['status'])) {
            throw SteadfastException::apiError('Invalid response format');
        }

        if ($response['status'] !== 200) {
            throw SteadfastException::apiError(
                $response['message'] ?? 'Unknown API error',
                ['response' => $response]
            );
        }

        return $response;

    }

    private function logRequest(array $logData): void
    {
        if (! $this->config['logging']['enabled']) {
            return;
        }
        try {
            SteadfastLog::create([
                'type' => $logData['type'],
                'request' => $logData['request'],
                'response' => $logData['response'],
                'endpoint' => $logData['endpoint'],
                'status_code' => $logData['status_code'],
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Steadfast logging failed: '.$e->getMessage());
        }
    }

    protected function validateBulkOrders(array $orders): array
    {
        return array_filter($orders, function ($order) {
            try {
                if ($order instanceof OrderRequest) {
                    $order->validate();

                    return true;
                }

                return false;
            } catch (Exception $e) {
                Log::warning('Invalid order filtered: '.$e->getMessage());

                return false;
            }
        });
    }

    protected function dispatchBulkOrderJob(array $orders): BulkOrderResponse
    {
        ProcessBulkOrders::dispatch($orders)
            ->onQueue($this->config['bulk']['queue_name']);

        return new BulkOrderResponse([
            'status' => 'queued',
            'message' => 'Bulk orders processing has been queued',
            'order_count' => count($orders),
        ]);
    }

    public function processBulkOrders(array $orders): BulkOrderResponse
    {
        $chunks = array_chunk($orders, $this->config['bulk']['chunk_size']);
        $responses = [];

        foreach ($chunks as $chunk) {
            try {
                $response = $this->makeRequest(
                    endpoint: '/create_order/bulk-order',
                    data: ['data' => $chunk],
                    type: 'bulk_order'
                );

                $responses[] = $this->handleBulkResponse($response);
            } catch (Exception $e) {
                $this->handleException($e, [
                    'chunk_size' => count($chunk),
                    'endpoint' => '/create_order/bulk-order',
                ]);
            }
        }

        return new BulkOrderResponse([
            'status' => 'processed',
            'data' => $responses,
            'success_count' => count(array_filter($responses, fn ($r) => $r['status'] === 'success')),
        ]);
    }

    protected function makeRequest(string $endpoint, array $data, string $type): array
    {
        $response = $this->httpClient
            ->retry(
                $this->config['retry']['times'],
                $this->config['retry']['sleep']
            )
            ->post($endpoint, $data)
            ->throw()
            ->json();

        $this->logRequest([
            'type' => $type,
            'request' => $data,
            'response' => $response,
            'endpoint' => $endpoint,
            'status_code' => $response['status'] ?? 500,
        ]);

        return $response;
    }

    /**
     * @throws SteadfastException
     */
    protected function handleBulkResponse(array $response): array
    {
        $processed = $this->handleResponse($response);

        return [
            'status' => 'success',
            'data' => $processed['data'] ?? [],
            'processed_at' => now()->toDateTimeString(),
        ];
    }
}
