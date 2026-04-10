<?php

namespace SabitAhmad\SteadFast\Services;

use Exception;
use Psr\Log\LoggerInterface;
use SabitAhmad\SteadFast\Models\SteadfastLog;

class SteadfastLogger
{
    public function __construct(
        protected LoggerInterface $logger,
        protected array $config = []
    ) {
        $this->config = $config ?: config('steadfast');
    }

    public function enabled(): bool
    {
        return $this->config['logging']['enabled'] ?? false;
    }

    public function log(array $logData): void
    {
        if (! $this->enabled()) {
            return;
        }

        $filteredRequest = $this->filterSensitiveData($logData['request'] ?? []);
        $responseData = $logData['response'] ?? [];

        if (isset($logData['duration_ms']) && is_array($responseData)) {
            $responseData['duration_ms'] = $logData['duration_ms'];
        }

        $filteredResponse = ($this->config['logging']['log_responses'] ?? false)
            ? $this->filterSensitiveData($responseData)
            : null;

        try {
            SteadfastLog::create([
                'type' => $logData['type'],
                'request' => ($this->config['logging']['log_requests'] ?? false) ? $filteredRequest : [],
                'response' => $filteredResponse,
                'endpoint' => $logData['endpoint'],
                'status_code' => $logData['status_code'],
                'error' => $logData['error'] ?? null,
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Steadfast logging failed: '.$e->getMessage(), [
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
}
