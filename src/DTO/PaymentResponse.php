<?php

namespace SabitAhmad\SteadFast\DTO;

class PaymentResponse
{
    public function __construct(
        public readonly int $id,
        public readonly float $amount,
        public readonly string $status,
        public readonly ?string $invoice = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly array $consignments = [],
        public readonly array $raw = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? $data['payment_id'] ?? 0),
            amount: (float) ($data['amount'] ?? $data['total_amount'] ?? 0),
            status: (string) ($data['status'] ?? 'unknown'),
            invoice: $data['invoice'] ?? $data['invoice_id'] ?? null,
            transactionId: $data['transaction_id'] ?? $data['trx_id'] ?? null,
            createdAt: $data['created_at'] ?? $data['date'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            consignments: $data['consignments'] ?? $data['orders'] ?? [],
            raw: $data
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'invoice' => $this->invoice,
            'transaction_id' => $this->transactionId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'consignments' => $this->consignments,
            'raw' => $this->raw,
        ];
    }

    public function isCompleted(): bool
    {
        return in_array(strtolower($this->status), ['completed', 'paid', 'success', 'cleared'], true);
    }

    public function isPending(): bool
    {
        return in_array(strtolower($this->status), ['pending', 'processing', 'in_review'], true);
    }

    public function getConsignmentCount(): int
    {
        return count($this->consignments);
    }
}
