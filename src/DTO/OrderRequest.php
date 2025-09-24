<?php

namespace SabitAhmad\SteadFast\DTO;

use SabitAhmad\SteadFast\Exceptions\SteadfastException;

class OrderRequest
{
    public function __construct(
        public string $invoice,
        public string $recipient_name,
        public string $recipient_phone,
        public string $recipient_address,
        public float $cod_amount,
        public ?string $alternative_phone = null,
        public ?string $recipient_email = null,
        public ?string $note = null,
        public ?string $item_description = null,
        public ?int $total_lot = null,
        public ?int $delivery_type = null // 0 = home delivery, 1 = point delivery/hub pickup
    ) {}

    public function toArray(): array
    {
        $data = [
            'invoice' => $this->invoice,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_address' => $this->recipient_address,
            'cod_amount' => $this->cod_amount,
        ];

        // Add optional fields only if they have values
        if ($this->alternative_phone !== null) {
            $data['alternative_phone'] = $this->alternative_phone;
        }

        if ($this->recipient_email !== null) {
            $data['recipient_email'] = $this->recipient_email;
        }

        if ($this->note !== null) {
            $data['note'] = $this->note;
        }

        if ($this->item_description !== null) {
            $data['item_description'] = $this->item_description;
        }

        if ($this->total_lot !== null) {
            $data['total_lot'] = $this->total_lot;
        }

        if ($this->delivery_type !== null) {
            $data['delivery_type'] = $this->delivery_type;
        }

        return $data;
    }

    /**
     * @throws SteadfastException
     */
    public function validate(): void
    {
        $rules = [
            'invoice' => 'required|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'recipient_name' => 'required|string|max:100',
            'recipient_phone' => 'required|string|regex:/^01[0-9]{9}$/',
            'recipient_address' => 'required|string|max:250',
            'cod_amount' => 'required|numeric|min:0',
            'alternative_phone' => 'nullable|string|regex:/^01[0-9]{9}$/',
            'recipient_email' => 'nullable|email|max:255',
            'note' => 'nullable|string|max:500',
            'item_description' => 'nullable|string|max:500',
            'total_lot' => 'nullable|integer|min:1',
            'delivery_type' => 'nullable|integer|in:0,1',
        ];

        $validator = validator($this->toArray(), $rules, [
            'invoice.regex' => 'Invoice must contain only alphanumeric characters, hyphens, and underscores.',
            'recipient_phone.regex' => 'Phone number must be 11 digits starting with 01.',
            'alternative_phone.regex' => 'Alternative phone number must be 11 digits starting with 01.',
            'delivery_type.in' => 'Delivery type must be 0 (home delivery) or 1 (point delivery).',
        ]);

        if ($validator->fails()) {
            throw SteadfastException::validationError($validator->errors()->toArray());
        }
    }

    /**
     * Create an order request from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoice: $data['invoice'],
            recipient_name: $data['recipient_name'],
            recipient_phone: $data['recipient_phone'],
            recipient_address: $data['recipient_address'],
            cod_amount: (float) $data['cod_amount'],
            alternative_phone: $data['alternative_phone'] ?? null,
            recipient_email: $data['recipient_email'] ?? null,
            note: $data['note'] ?? null,
            item_description: $data['item_description'] ?? null,
            total_lot: isset($data['total_lot']) ? (int) $data['total_lot'] : null,
            delivery_type: isset($data['delivery_type']) ? (int) $data['delivery_type'] : null
        );
    }

    /**
     * Check if this is a home delivery order
     */
    public function isHomeDelivery(): bool
    {
        return $this->delivery_type === null || $this->delivery_type === 0;
    }

    /**
     * Check if this is a point delivery order
     */
    public function isPointDelivery(): bool
    {
        return $this->delivery_type === 1;
    }
}
