<?php

namespace SabitAhmad\SteadFast\DTO;

class BulkOrderResponse
{
    public string $status;
    public string $message;
    public ?array $data;
    public ?int $order_count;
    public ?int $success_count;

    public function __construct(array $responseData)
    {
        $this->status = $responseData['status'] ?? 'error';
        $this->message = $responseData['message'] ?? '';
        $this->data = $responseData['data'] ?? null;
        $this->order_count = $responseData['order_count'] ?? null;
        $this->success_count = $responseData['success_count'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'order_count' => $this->order_count,
            'success_count' => $this->success_count
        ];
    }
}
