<?php

namespace SabitAhmad\SteadFast\DTO;

class PoliceStationResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $district = null,
        public readonly ?string $division = null,
        public readonly ?string $code = null,
        public readonly array $raw = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? $data['police_station'] ?? $data['thana'] ?? ''),
            district: $data['district'] ?? $data['district_name'] ?? null,
            division: $data['division'] ?? $data['division_name'] ?? null,
            code: isset($data['code']) ? (string) $data['code'] : null,
            raw: $data
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'district' => $this->district,
            'division' => $this->division,
            'code' => $this->code,
            'raw' => $this->raw,
        ];
    }
}
