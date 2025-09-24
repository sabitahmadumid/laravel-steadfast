<?php

namespace SabitAhmad\SteadFast\Commands;

use Illuminate\Console\Command;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use SabitAhmad\SteadFast\SteadFast;

class SteadfastTestCommand extends Command
{
    protected $signature = 'steadfast:test';

    protected $description = 'Test Steadfast API connection and configuration';

    public function handle(): void
    {
        $this->info('Testing Steadfast API Configuration...');
        $this->info(str_repeat('=', 50));

        // Test configuration
        $this->testConfiguration();

        // Test API connection
        $this->testApiConnection();

        $this->info("\nTest completed!");
    }

    private function testConfiguration(): void
    {
        $this->info("\n1. Configuration Test:");

        $apiKey = config('steadfast.api_key');
        $secretKey = config('steadfast.secret_key');
        $baseUrl = config('steadfast.base_url');

        if (empty($apiKey)) {
            $this->error('   ✗ API Key is missing');
        } else {
            $this->info('   ✓ API Key is configured');
        }

        if (empty($secretKey)) {
            $this->error('   ✗ Secret Key is missing');
        } else {
            $this->info('   ✓ Secret Key is configured');
        }

        if (empty($baseUrl)) {
            $this->error('   ✗ Base URL is missing');
        } else {
            $this->info("   ✓ Base URL: {$baseUrl}");
        }

        // Test other configuration
        $timeout = config('steadfast.timeout', 30);
        $bulkQueue = config('steadfast.bulk.queue', true);
        $logging = config('steadfast.logging.enabled', true);

        $this->info("   ✓ Timeout: {$timeout}s");
        $this->info('   ✓ Bulk Queue: '.($bulkQueue ? 'enabled' : 'disabled'));
        $this->info('   ✓ Logging: '.($logging ? 'enabled' : 'disabled'));
    }

    private function testApiConnection(): void
    {
        $this->info("\n2. API Connection Test:");

        try {
            $steadfast = new SteadFast;
            $result = $steadfast->testConnection();

            $this->info('   ✓ API connection successful');
            $this->info('   ✓ Current balance: '.number_format($result['balance'], 2).' BDT');
        } catch (SteadfastException $e) {
            $this->error('   ✗ API connection failed: '.$e->getMessage());

            if ($e->getCode() === 401) {
                $this->error('   → Check your API credentials');
            } elseif ($e->getCode() === 503) {
                $this->error('   → Steadfast API is currently unavailable');
            } elseif ($e->getCode() === 404) {
                $this->error('   → Check your base URL configuration');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Unexpected error: '.$e->getMessage());
        }
    }
}
