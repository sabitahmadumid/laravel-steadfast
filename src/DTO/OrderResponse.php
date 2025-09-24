<?php

namespace SabitAhmad\SteadFast\DTO;

class OrderResponse
{
    public function __construct(
        public int $status,
        public string $message,
        public array $consignment
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'consignment' => $this->consignment,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            message: $data['message'],
            consignment: $data['consignment']
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === 200;
    }

    public function getConsignmentId(): ?int
    {
        return $this->consignment['consignment_id'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->consignment['tracking_code'] ?? null;
    }

    public function getInvoice(): ?string
    {
        return $this->consignment['invoice'] ?? null;
    }

    public function getOrderStatus(): ?string
    {
        return $this->consignment['status'] ?? null;
    }

    public function getCodAmount(): ?float
    {
        return isset($this->consignment['cod_amount'])
            ? (float) $this->consignment['cod_amount']
            : null;
    }
}
