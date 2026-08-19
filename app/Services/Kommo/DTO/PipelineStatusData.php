<?php

namespace App\Services\Kommo\DTO;

final readonly class PipelineStatusData
{
    public function __construct(
        public int $id,
        public string $name,
        public int $sort,
        public ?string $color,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        return new self(
            id: (int) $raw['id'],
            name: (string) ($raw['name'] ?? ''),
            sort: (int) ($raw['sort'] ?? 0),
            color: $raw['color'] ?? null,
        );
    }
}
