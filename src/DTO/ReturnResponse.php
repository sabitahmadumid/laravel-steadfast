<?php

namespace SabitAhmad\SteadFast\DTO;

class ReturnResponse
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $consignment_id,
        public ?string $reason,
        public string $status,
        public string $created_at,
        public string $updated_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'consignment_id' => $this->consignment_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            user_id: $data['user_id'],
            consignment_id: $data['consignment_id'],
            reason: $data['reason'],
            status: $data['status'],
            created_at: $data['created_at'],
            updated_at: $data['updated_at']
        );
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getStatusDescription(): string
    {
        return match ($this->status) {
            'pending' => 'Return request is pending approval.',
            'approved' => 'Return request has been approved.',
            'processing' => 'Return request is being processed.',
            'completed' => 'Return request has been completed.',
            'cancelled' => 'Return request has been cancelled.',
            default => 'Unknown status: '.$this->status
        };
    }
}
