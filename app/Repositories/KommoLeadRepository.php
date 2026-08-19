<?php

namespace App\Repositories;

use App\Services\Kommo\CustomFieldMap;
use App\Services\Kommo\DTO\ContactData;
use App\Services\Kommo\DTO\KommoUserData;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\DTO\NoteData;
use App\Services\Kommo\DTO\PipelineData;
use App\Services\Kommo\DTO\TaskData;
use App\Services\Kommo\KommoClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class KommoLeadRepository implements LeadRepository
{
    /**
     * Teto de páginas por consulta base (40 × 250 = 10.000 leads).
     * Consultas maiores que isso devem virar snapshot via job.
     */
    private const MAX_PAGES = 40;

    public function __construct(
        private readonly KommoClient $client,
        private readonly CustomFieldMap $fieldMap,
    ) {}

    public function leads(LeadFilters $filters): Collection
    {
        $fieldIds = $this->fieldIds();

        return collect($this->rawLeads($filters))
            ->map(fn (array $raw): LeadData => LeadData::fromApi($raw, $fieldIds))
            ->filter(fn (LeadData $lead): bool => $filters->matches($lead))
            ->values();
    }

    public function paginateLeads(
        LeadFilters $filters,
        int $page,
        int $perPage,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
    ): LengthAwarePaginator {
        $leads = $this->leads($filters);

        if ($sortColumn !== null) {
            $rows = $leads->map(fn (LeadData $lead): array => $lead->toRow());

            $rows = $sortDirection === 'desc'
                ? $rows->sortByDesc($sortColumn)
                : $rows->sortBy($sortColumn);

            $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

            return new Paginator(
                items: $items,
                total: $leads->count(),
                perPage: $perPage,
                currentPage: $page,
            );
        }

        $items = $leads
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(fn (LeadData $lead): array => $lead->toRow())
            ->values();

        return new Paginator(
            items: $items,
            total: $leads->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function find(int $leadId): ?LeadData
    {
        $body = $this->client->get("/leads/{$leadId}", ['with' => 'contacts,source,loss_reason']);

        if ($body === [] || ! isset($body['id'])) {
            return null;
        }

        return LeadData::fromApi($body, $this->fieldIds());
    }

    public function notes(int $leadId): Collection
    {
        $items = collect();

        foreach ($this->client->paginate("/leads/{$leadId}/notes", [], 'notes') as $raw) {
            $items->push(NoteData::fromApi($raw));
        }

        return $items->sortByDesc(fn (NoteData $note) => $note->createdAt->getTimestamp())->values();
    }

    public function openTasks(int $leadId): Collection
    {
        $items = collect();

        $query = [
            'filter' => [
                'entity_type' => 'leads',
                'entity_id' => $leadId,
                'is_completed' => 0,
            ],
        ];

        foreach ($this->client->paginate('/tasks', $query, 'tasks') as $raw) {
            $items->push(TaskData::fromApi($raw));
        }

        return $items;
    }

    public function contacts(array $contactIds): Collection
    {
        if ($contactIds === []) {
            return collect();
        }

        $items = collect();

        $query = ['filter' => ['id' => array_values($contactIds)]];

        foreach ($this->client->paginate('/contacts', $query, 'contacts') as $raw) {
            $items->push(ContactData::fromApi($raw));
        }

        return $items;
    }

    public function pipelines(): Collection
    {
        $raw = Cache::remember(
            'kommo:pipelines',
            config('kommo.cache.pipelines_ttl'),
            function (): array {
                $body = $this->client->get('/leads/pipelines');

                return $body['_embedded']['pipelines'] ?? [];
            },
        );

        return collect($raw)->map(fn (array $p): PipelineData => PipelineData::fromApi($p))->values();
    }

    public function users(): Collection
    {
        $raw = Cache::remember(
            'kommo:users',
            config('kommo.cache.users_ttl'),
            fn (): array => iterator_to_array($this->client->paginate('/users', [], 'users')),
        );

        return collect($raw)->map(fn (array $u): KommoUserData => KommoUserData::fromApi($u))->values();
    }

    public function fetchedAt(LeadFilters $filters): ?CarbonImmutable
    {
        $meta = Cache::get($filters->baseKey());

        return isset($meta['fetched_at'])
            ? CarbonImmutable::createFromTimestampUTC($meta['fetched_at'])
            : null;
    }

    public function invalidate(LeadFilters $filters): void
    {
        Cache::forget($filters->baseKey());
    }

    /**
     * Consulta base na API (pipelines + período + busca), cacheada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rawLeads(LeadFilters $filters): array
    {
        $cached = Cache::remember(
            $filters->baseKey(),
            config('kommo.cache.list_ttl'),
            function () use ($filters): array {
                $leads = iterator_to_array(
                    $this->client->paginate('/leads', $filters->toApiQuery(), 'leads', self::MAX_PAGES),
                    preserve_keys: false,
                );

                return [
                    'fetched_at' => now()->getTimestamp(),
                    'leads' => $leads,
                ];
            },
        );

        return $cached['leads'] ?? [];
    }

    /**
     * @return array<string, int|null>
     */
    private function fieldIds(): array
    {
        $ids = [];

        foreach (array_keys(config('kommo.custom_fields')) as $key) {
            $ids[$key] = $this->fieldMap->idFor($key);
        }

        return $ids;
    }
}
