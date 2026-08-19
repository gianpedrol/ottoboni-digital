<?php

namespace App\Services\Kommo\DTO;

use Carbon\CarbonImmutable;

final readonly class NoteData
{
    public function __construct(
        public int $id,
        public string $noteType,
        public string $text,
        public ?int $createdBy,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        $params = $raw['params'] ?? [];

        $text = $params['text']
            ?? $params['message']
            ?? $params['service']
            ?? '';

        return new self(
            id: (int) $raw['id'],
            noteType: (string) ($raw['note_type'] ?? ''),
            text: (string) $text,
            createdBy: isset($raw['created_by']) ? (int) $raw['created_by'] : null,
            createdAt: CarbonImmutable::createFromTimestampUTC((int) $raw['created_at']),
        );
    }

    /**
     * Nota escrita por um humano (created_by > 0 exclui robôs e integrações,
     * que o Kommo registra com id 0).
     */
    public function humana(): bool
    {
        return $this->noteType === 'common' && ($this->createdBy ?? 0) > 0;
    }
}
