<?php

namespace App\Services\Kommo\DTO;

final readonly class KommoUserData
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        return new self(
            id: (int) $raw['id'],
            name: (string) ($raw['name'] ?? ''),
        );
    }
}
