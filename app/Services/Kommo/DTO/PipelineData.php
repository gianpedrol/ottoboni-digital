<?php

namespace App\Services\Kommo\DTO;

final readonly class PipelineData
{
    /**
     * @param  array<int, PipelineStatusData>  $statuses  ordenados por sort
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $statuses,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        $statuses = array_map(
            fn (array $s): PipelineStatusData => PipelineStatusData::fromApi($s),
            $raw['_embedded']['statuses'] ?? [],
        );

        usort($statuses, fn (PipelineStatusData $a, PipelineStatusData $b): int => $a->sort <=> $b->sort);

        return new self(
            id: (int) $raw['id'],
            name: (string) ($raw['name'] ?? ''),
            statuses: $statuses,
        );
    }

    public function statusName(int $statusId): ?string
    {
        foreach ($this->statuses as $status) {
            if ($status->id === $statusId) {
                return $status->name;
            }
        }

        return null;
    }
}
