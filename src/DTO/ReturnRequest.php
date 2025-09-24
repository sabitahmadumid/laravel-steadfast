<?php

namespace SabitAhmad\SteadFast\DTO;

class ReturnRequest
{
    public function __construct(
        public string|int $identifier, // consignment_id, invoice, or tracking_code
        public string $identifier_type, // 'consignment_id', 'invoice', or 'tracking_code'
        public ?string $reason = null
    ) {}

    public function toArray(): array
    {
        $data = [];

        // Set the appropriate identifier field
        $data[$this->identifier_type] = $this->identifier;

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    public static function byConsignmentId(int $consignmentId, ?string $reason = null): self
    {
        return new self($consignmentId, 'consignment_id', $reason);
    }

    public static function byInvoice(string $invoice, ?string $reason = null): self
    {
        return new self($invoice, 'invoice', $reason);
    }

    public static function byTrackingCode(string $trackingCode, ?string $reason = null): self
    {
        return new self($trackingCode, 'tracking_code', $reason);
    }
}
