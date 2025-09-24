<?php

namespace SabitAhmad\SteadFast\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;

class BulkOrderCompleted
{
    use Dispatchable, SerializesModels;

    public BulkOrderResponse $response;
    public string $uniqueId;

    public function __construct(BulkOrderResponse $response, string $uniqueId)
    {
        $this->response = $response;
        $this->uniqueId = $uniqueId;
    }
}
