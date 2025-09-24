<?php

namespace SabitAhmad\SteadFast\Jobs;

use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\Events\BulkOrderCompleted;
use SabitAhmad\SteadFast\Events\BulkOrderFailed;
use SabitAhmad\SteadFast\Events\BulkOrderStarted;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use SabitAhmad\SteadFast\Models\SteadfastLog;
use SabitAhmad\SteadFast\SteadFast;
use Throwable;

class ProcessBulkOrders implements ShouldQueue, ShouldBeUnique
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The maximum number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff;

    /**
     * The number of seconds after which the job should timeout.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The orders to be processed.
     */
    protected array $orders;

    /**
     * Unique job identifier
     */
    protected string $uniqueId;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
        $this->uniqueId = md5(serialize($orders) . time());
        
        // Get configuration values
        $config = config('steadfast.bulk', []);
        $this->tries = $config['max_attempts'] ?? 3;
        $this->backoff = $config['backoff_seconds'] ?? 60;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'bulk_orders_' . $this->uniqueId;
    }

    /**
     * @throws SteadfastException
     */
    public function handle(SteadFast $steadFast): void
    {
        // Skip if job is part of a batch that has been cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->logJobStart();
        
        // Fire event for job start
        if (class_exists('SabitAhmad\SteadFast\Events\BulkOrderStarted')) {
            Event::dispatch(new BulkOrderStarted($this->orders, $this->uniqueId));
        }

        try {
            $response = $steadFast->processBulkOrders($this->orders);

            if (!$response->isSuccessful()) {
                throw new SteadfastException(
                    'Bulk order processing failed: ' . $response->message,
                    500,
                    null,
                    $response->toArray()
                );
            }

            $this->logSuccess($response);
            
            // Fire event for job completion
            if (class_exists('SabitAhmad\SteadFast\Events\BulkOrderCompleted')) {
                Event::dispatch(new BulkOrderCompleted($response, $this->uniqueId));
            }

        } catch (SteadfastException $e) {
            $this->handleSteadfastException($e);
        } catch (Exception $e) {
            $this->handleGenericException($e);
        }
    }

    protected function logJobStart(): void
    {
        if (config('steadfast.logging.enabled')) {
            SteadfastLog::create([
                'type' => 'bulk_job_start',
                'request' => [
                    'order_count' => count($this->orders),
                    'unique_id' => $this->uniqueId,
                    'job_id' => $this->job?->getJobId(),
                    'queue' => $this->queue,
                    'attempts' => $this->attempts(),
                ],
                'response' => null,
                'endpoint' => 'internal',
                'status_code' => 200,
            ]);
        }
    }

    /**
     * @throws SteadfastException
     */
    protected function handleSteadfastException(SteadfastException $e): void
    {
        $this->logError($e);

        // Fire error event
        if (class_exists('SabitAhmad\SteadFast\Events\BulkOrderFailed')) {
            Event::dispatch(new BulkOrderFailed($e, $this->orders, $this->uniqueId));
        }

        if ($this->attempts() >= $this->tries) {
            $this->fail($e);
            return;
        }

        // Calculate exponential backoff
        $delay = $this->backoff * pow(2, $this->attempts() - 1);
        $this->release($delay);
    }

    protected function handleGenericException(Exception $e): void
    {
        $this->logError($e);
        
        // Fire error event
        if (class_exists('SabitAhmad\SteadFast\Events\BulkOrderFailed')) {
            Event::dispatch(new BulkOrderFailed($e, $this->orders, $this->uniqueId));
        }

        $this->fail($e);
    }

    private function logError(Throwable $e): void
    {
        if (config('steadfast.logging.enabled')) {
            SteadfastLog::create([
                'type' => 'bulk_job_error',
                'request' => [
                    'order_count' => count($this->orders),
                    'unique_id' => $this->uniqueId,
                    'job_id' => $this->job?->getJobId(),
                    'attempts' => $this->attempts(),
                    'orders' => array_map(function ($order) {
                        return $order instanceof OrderRequest 
                            ? ['invoice' => $order->invoice, 'cod_amount' => $order->cod_amount]
                            : 'Invalid order';
                    }, array_slice($this->orders, 0, 5)), // Log first 5 orders for debugging
                ],
                'response' => $e instanceof SteadfastException ? $e->getContext() : [],
                'endpoint' => 'internal',
                'status_code' => $e->getCode() ?: 500,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::emergency('Bulk order job permanently failed', [
            'unique_id' => $this->uniqueId,
            'job_id' => $this->job?->getJobId(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'order_count' => count($this->orders),
            'orders' => array_map(function ($order) {
                return $order instanceof OrderRequest ? $order->invoice : 'Invalid order';
            }, $this->orders),
        ]);

        // Log final failure
        if (config('steadfast.logging.enabled')) {
            SteadfastLog::create([
                'type' => 'bulk_job_failed',
                'request' => [
                    'order_count' => count($this->orders),
                    'unique_id' => $this->uniqueId,
                    'job_id' => $this->job?->getJobId(),
                ],
                'response' => null,
                'endpoint' => 'internal',
                'status_code' => $exception->getCode() ?: 500,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function displayName(): string
    {
        return 'Steadfast Bulk Orders: ' . count($this->orders) . ' orders [' . $this->uniqueId . ']';
    }

    public function tags(): array
    {
        return [
            'steadfast',
            'bulk_orders',
            'count:' . count($this->orders),
            'unique_id:' . $this->uniqueId,
        ];
    }

    private function logSuccess(BulkOrderResponse $response): void
    {
        if (config('steadfast.logging.enabled')) {
            SteadfastLog::create([
                'type' => 'bulk_job_success',
                'request' => [
                    'order_count' => count($this->orders),
                    'unique_id' => $this->uniqueId,
                    'job_id' => $this->job?->getJobId(),
                ],
                'response' => [
                    'success_count' => $response->success_count,
                    'error_count' => $response->error_count,
                    'success_rate' => $response->getSuccessRate(),
                ],
                'endpoint' => 'internal',
                'status_code' => 200,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Get order summary for logging
     */
    private function getOrderSummary(): array
    {
        return array_map(function ($order) {
            if ($order instanceof OrderRequest) {
                return [
                    'invoice' => $order->invoice,
                    'cod_amount' => $order->cod_amount,
                    'delivery_type' => $order->delivery_type,
                ];
            }
            return 'Invalid order type';
        }, array_slice($this->orders, 0, 10)); // Log first 10 orders
    }
}
