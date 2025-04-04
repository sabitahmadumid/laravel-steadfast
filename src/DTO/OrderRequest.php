<?php

namespace SabitAhmad\SteadFast\DTO;

class OrderRequest
{
    public function __construct(
        public string $invoice,
        public string $recipient_name,
        public string $recipient_phone,
        public string $recipient_address,
        public float $cod_amount,
        public ?string $note = null
    ) {}

    public function toArray(): array
    {
        return [
            'invoice' => $this->invoice,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_address' => $this->recipient_address,
            'cod_amount' => $this->cod_amount,
            'note' => $this->note,
        ];
    }

    public function validate(): void
    {
        // Add validation logic using Laravel validator
        validator($this->toArray(), [
            'invoice' => 'required|unique:steadfast_orders,invoice',
            'recipient_name' => 'required|max:100',
            'recipient_phone' => 'required|digits:11',
            'recipient_address' => 'required|max:250',
            'cod_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ])->validate();
    }
}
