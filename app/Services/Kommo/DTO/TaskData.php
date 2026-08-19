<?php

namespace App\Services\Kommo\DTO;

use Carbon\CarbonImmutable;

final readonly class TaskData
{
    public function __construct(
        public int $id,
        public string $text,
        public bool $isCompleted,
        public ?CarbonImmutable $completeTill,
        public ?int $responsibleUserId,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        return new self(
            id: (int) $raw['id'],
            text: (string) ($raw['text'] ?? ''),
            isCompleted: (bool) ($raw['is_completed'] ?? false),
            completeTill: isset($raw['complete_till']) ? CarbonImmutable::createFromTimestampUTC((int) $raw['complete_till']) : null,
            responsibleUserId: isset($raw['responsible_user_id']) ? (int) $raw['responsible_user_id'] : null,
        );
    }
}
