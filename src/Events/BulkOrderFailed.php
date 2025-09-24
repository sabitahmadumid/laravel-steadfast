<?php

namespace SabitAhmad\SteadFast\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class BulkOrderFailed
{
    use Dispatchable, SerializesModels;

    public Throwable $exception;
    public array $orders;
    public string $uniqueId;

    public function __construct(Throwable $exception, array $orders, string $uniqueId)
    {
        $this->exception = $exception;
        $this->orders = $orders;
        $this->uniqueId = $uniqueId;
    }
}
