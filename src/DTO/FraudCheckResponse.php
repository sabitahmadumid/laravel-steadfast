<?php

namespace SabitAhmad\SteadFast\DTO;

class FraudCheckResponse
{
    public function __construct(
        public readonly int $success,
        public readonly int $cancel,
        public readonly int $total,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $error = null
    ) {}

    /**
     * Create a FraudCheckResponse from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? 0,
            cancel: $data['cancel'] ?? 0,
            total: $data['total'] ?? 0,
            phoneNumber: $data['phone_number'] ?? null,
            error: $data['error'] ?? null
        );
    }

    /**
     * Convert the response to an array.
     */
    public function toArray(): array
    {
        $array = [
            'success' => $this->success,
            'cancel' => $this->cancel,
            'total' => $this->total,
        ];

        if ($this->phoneNumber !== null) {
            $array['phone_number'] = $this->phoneNumber;
        }

        if ($this->error !== null) {
            $array['error'] = $this->error;
        }

        return $array;
    }

    /**
     * Check if the fraud check was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->error === null;
    }

    /**
     * Check if the response has an error.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get the success rate as a percentage.
     */
    public function getSuccessRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return round(($this->success / $this->total) * 100, 2);
    }

    /**
     * Get the cancellation rate as a percentage.
     */
    public function getCancelRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return round(($this->cancel / $this->total) * 100, 2);
    }

    /**
     * Check if the customer is considered risky based on cancellation rate.
     */
    public function isRisky(int $threshold = 50): bool
    {
        return $this->getCancelRate() >= $threshold;
    }

    /**
     * Get a risk assessment based on the cancellation rate.
     */
    public function getRiskLevel(): string
    {
        $cancelRate = $this->getCancelRate();

        return match (true) {
            $cancelRate >= 75 => 'very_high',
            $cancelRate >= 50 => 'high',
            $cancelRate >= 25 => 'medium',
            $cancelRate > 0 => 'low',
            default => 'none',
        };
    }

    /**
     * Get a human-readable risk assessment.
     */
    public function getRiskDescription(): string
    {
        return match ($this->getRiskLevel()) {
            'very_high' => 'Very High Risk - Consider declining order',
            'high' => 'High Risk - Proceed with caution',
            'medium' => 'Medium Risk - Monitor closely',
            'low' => 'Low Risk - Safe to proceed',
            'none' => 'No Risk - New or reliable customer',
            default => 'Unknown Risk',
        };
    }
}
