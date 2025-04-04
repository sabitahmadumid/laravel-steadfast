<?php

namespace SabitAhmad\SteadFast\DTO;

class BulkOrderResponse
{
    public function __construct(
        public string $status,
        public ?array $data = null,
        public ?string $message = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['status'] ?? 'error',
            $data['data'] ?? null,
            $data['message'] ?? null
        );
    }
}
