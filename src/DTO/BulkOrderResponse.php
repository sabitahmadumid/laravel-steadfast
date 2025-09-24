<?php

namespace SabitAhmad\SteadFast\DTO;

class BulkOrderResponse
{
    public string $status;

    public string $message;

    public ?array $data;

    public ?int $order_count;

    public ?int $success_count;

    public ?int $error_count;

    public ?array $errors;

    public function __construct(array $responseData)
    {
        $this->status = $responseData['status'] ?? 'error';
        $this->message = $responseData['message'] ?? '';
        $this->data = $responseData['data'] ?? null;
        $this->order_count = $responseData['order_count'] ?? null;
        $this->success_count = $responseData['success_count'] ?? null;
        $this->error_count = $responseData['error_count'] ?? null;
        $this->errors = $responseData['errors'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'order_count' => $this->order_count,
            'success_count' => $this->success_count,
            'error_count' => $this->error_count,
            'errors' => $this->errors,
        ];
    }

    public static function fromApiResponse(array $data): self
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($data as $item) {
            if (isset($item['status'])) {
                if ($item['status'] === 'success') {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = $item;
                }
            }
        }

        return new self([
            'status' => 'processed',
            'message' => "Processed {$successCount} successful orders, {$errorCount} failed",
            'data' => $data,
            'order_count' => count($data),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ]);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'processed' || $this->status === 'queued';
    }

    public function hasErrors(): bool
    {
        return $this->error_count > 0;
    }

    public function getSuccessRate(): float
    {
        if ($this->order_count === 0) {
            return 0.0;
        }

        return ($this->success_count / $this->order_count) * 100;
    }

    public function getSuccessfulOrders(): array
    {
        if (!$this->data) {
            return [];
        }

        return array_filter($this->data, fn($item) => ($item['status'] ?? '') === 'success');
    }

    public function getFailedOrders(): array
    {
        if (!$this->data) {
            return [];
        }

        return array_filter($this->data, fn($item) => ($item['status'] ?? '') !== 'success');
    }
}
