<?php

namespace SabitAhmad\SteadFast\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;
use SabitAhmad\SteadFast\Models\SteadfastLog;
use SabitAhmad\SteadFast\SteadFast;
use Throwable;

class ProcessBulkOrders implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The maximum number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60;

    /**
     * The orders to be processed.
     *
     * @var array
     */
    protected array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }


    /**
     * @throws SteadfastException
     */
    public function handle(SteadFast $steadFast): void
    {

        $this->logJobStart();
        try {
            $response = $steadFast->processBulkOrders($this->orders);

            if ($response->status !== 'processed') {
                throw new SteadfastException(
                    'Unexpected processing status: ' . $response->status,
                    500,
                    null,
                    $response->toArray()
                );
            }

            $this->logSuccess($response);

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
                'request' => ['order_count' => count($this->orders)],
                'response' => null,
                'endpoint' => 'internal',
                'status_code' => 200
            ]);
        }
    }

    /**
     * @throws SteadfastException
     */
    protected function handleSteadfastException(SteadfastException $e): void
    {

        if (config('steadfast.logging.enabled')) {
            SteadfastLog::create([
                'type' => 'bulk_job_error',
                'request' => $this->orders,
                'response' => $e->getContext(),
                'endpoint' => 'internal',
                'status_code' => $e->getCode(),
                'error' => $e->getMessage()
            ]);
        }

        if ($this->attempts() >= $this->tries) {
            $this->fail($e);
        }

        // Re-throw for immediate retry
        throw $e;
    }

    protected function handleGenericException(Exception $e): void
    {
        $this->fail($e);
    }

    public function failed(Throwable $exception): void
    {
        Log::emergency('Bulk order job failed after all attempts', [
            'job_id' => $this->job->getJobId(),
            'exception' => $exception->getMessage(),
            'orders' => array_map(function ($order) {
                return $order instanceof OrderRequest ? $order->invoice : 'Invalid order';
            }, $this->orders)
        ]);
    }

    public function displayName(): string
    {
        return 'Steadfast Bulk Orders: ' . count($this->orders) . ' orders';
    }

    public function tags(): array
    {
        return [
            'steadfast',
            'bulk_orders',
            'count:'.count($this->orders)
        ];
    }

    private function logSuccess(BulkOrderResponse $response): void
    {
        if (config('steadfast.logging.enabled')) {
            SteadfastLog::create([
                'type' => 'bulk_job_success',
                'request' => ['order_count' => count($this->orders)],
                'response' => $response->toArray(),
                'endpoint' => 'internal',
                'status_code' => 200,
                'created_at' => now()
            ]);
        }
    }


}
