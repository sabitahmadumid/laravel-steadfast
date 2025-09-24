<?php

namespace SabitAhmad\SteadFast\DTO;

class BalanceResponse
{
    public function __construct(
        public int $status,
        public float $current_balance,
        public ?string $message = null
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'current_balance' => $this->current_balance,
            'message' => $this->message,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            current_balance: (float) $data['current_balance'],
            message: $data['message'] ?? null
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === 200;
    }

    public function getFormattedBalance(): string
    {
        return number_format($this->current_balance, 2).' BDT';
    }
}
