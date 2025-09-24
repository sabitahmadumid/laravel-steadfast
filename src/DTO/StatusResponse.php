<?php

namespace SabitAhmad\SteadFast\DTO;

class StatusResponse
{
    public function __construct(
        public int $status,
        public string $delivery_status,
        public ?string $message = null
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'delivery_status' => $this->delivery_status,
            'message' => $this->message,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            delivery_status: $data['delivery_status'],
            message: $data['message'] ?? null
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === 200;
    }

    public function isDelivered(): bool
    {
        return in_array($this->delivery_status, ['delivered', 'partial_delivered']);
    }

    public function isCancelled(): bool
    {
        return in_array($this->delivery_status, ['cancelled', 'cancelled_approval_pending']);
    }

    public function isPending(): bool
    {
        return in_array($this->delivery_status, [
            'pending',
            'in_review',
            'delivered_approval_pending',
            'partial_delivered_approval_pending',
            'cancelled_approval_pending',
            'unknown_approval_pending'
        ]);
    }

    public function isOnHold(): bool
    {
        return $this->delivery_status === 'hold';
    }

    public function getStatusDescription(): string
    {
        return match ($this->delivery_status) {
            'pending' => 'Consignment is not delivered or cancelled yet.',
            'delivered_approval_pending' => 'Consignment is delivered but waiting for admin approval.',
            'partial_delivered_approval_pending' => 'Consignment is delivered partially and waiting for admin approval.',
            'cancelled_approval_pending' => 'Consignment is cancelled and waiting for admin approval.',
            'unknown_approval_pending' => 'Unknown Pending status. Need contact with the support team.',
            'delivered' => 'Consignment is delivered and balance added.',
            'partial_delivered' => 'Consignment is partially delivered and balance added.',
            'cancelled' => 'Consignment is cancelled and balance updated.',
            'hold' => 'Consignment is held.',
            'in_review' => 'Order is placed and waiting to be reviewed.',
            'unknown' => 'Unknown status. Need contact with the support team.',
            default => 'Unknown status: ' . $this->delivery_status
        };
    }
}
