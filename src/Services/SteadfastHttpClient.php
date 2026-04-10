<?php

namespace SabitAhmad\SteadFast\Services;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use Throwable;

class SteadfastHttpClient
{
    protected PendingRequest $client;

    public function __construct(
        protected Factory $http,
        protected SteadfastLogger $logger,
        protected array $config = []
    ) {
        $this->config = $config ?: config('steadfast');
        $this->client = $this->buildClient();
    }

    public function get(string $endpoint, string $type, array $request = []): array
    {
        return $this->request('get', $endpoint, [], $type, $request);
    }

    public function post(string $endpoint, array $data, string $type): array
    {
        return $this->request('post', $endpoint, $data, $type, $data);
    }

    /**
     * @throws SteadfastException
     */
    public function validateResponse(array $response): array
    {
        if (! isset($response['status'])) {
            throw SteadfastException::apiError('Invalid response format: missing status field', $response);
        }

        if ($response['status'] !== 200) {
            throw SteadfastException::apiError($response['message'] ?? 'Unknown API error', $response);
        }

        return $response;
    }

    protected function request(string $method, string $endpoint, array $payload, string $type, array $requestContext): array
    {
        $startTime = microtime(true);

        try {
            $response = $this->client->{$method}($endpoint, $payload)
                ->throw()
                ->json();

            $this->logger->log([
                'type' => $type,
                'request' => $requestContext,
                'response' => $response,
                'endpoint' => $endpoint,
                'status_code' => $response['status'] ?? 500,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $response;
        } catch (RequestException $e) {
            $this->logger->log([
                'type' => $type.'_error',
                'request' => $requestContext,
                'response' => $e->response?->json() ?? [],
                'endpoint' => $endpoint,
                'status_code' => $e->response?->status() ?? 500,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function buildClient(): PendingRequest
    {
        return $this->http->baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->connectTimeout($this->config['connect_timeout'] ?? 10)
            ->withHeaders([
                'Api-Key' => $this->config['api_key'],
                'Secret-Key' => $this->config['secret_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-SteadFast-Package/2.2.1',
            ])
            ->retry(
                $this->config['retry']['times'],
                $this->config['retry']['sleep'],
                fn (Throwable $exception): bool => $this->shouldRetry($exception)
            );
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return false;
        }

        return in_array(
            $exception->response?->status(),
            $this->config['retry']['when'] ?? [500, 502, 503, 504, 429],
            true
        );
    }
}
