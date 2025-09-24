<?php

namespace SabitAhmad\SteadFast\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkOrderStarted
{
    use Dispatchable, SerializesModels;

    public array $orders;

    public string $uniqueId;

    public function __construct(array $orders, string $uniqueId)
    {
        $this->orders = $orders;
        $this->uniqueId = $uniqueId;
    }
}
