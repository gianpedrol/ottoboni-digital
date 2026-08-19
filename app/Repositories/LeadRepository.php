<?php

namespace App\Repositories;

use App\Services\Kommo\DTO\ContactData;
use App\Services\Kommo\DTO\KommoUserData;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\DTO\NoteData;
use App\Services\Kommo\DTO\PipelineData;
use App\Services\Kommo\DTO\TaskData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface para permitir trocar a fonte (API ao vivo → lead_snapshots)
 * sem tocar em tela nenhuma, caso o plano B da seção 5.4 seja necessário.
 */
interface LeadRepository
{
    /**
     * Todos os leads que casam com os filtros (base + memória), do cache.
     *
     * @return Collection<int, LeadData>
     */
    public function leads(LeadFilters $filters): Collection;

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateLeads(
        LeadFilters $filters,
        int $page,
        int $perPage,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
    ): LengthAwarePaginator;

    public function find(int $leadId): ?LeadData;

    /**
     * @return Collection<int, NoteData>
     */
    public function notes(int $leadId): Collection;

    /**
     * @return Collection<int, TaskData>
     */
    public function openTasks(int $leadId): Collection;

    /**
     * @param  array<int>  $contactIds
     * @return Collection<int, ContactData>
     */
    public function contacts(array $contactIds): Collection;

    /**
     * @return Collection<int, PipelineData>
     */
    public function pipelines(): Collection;

    /**
     * @return Collection<int, KommoUserData>
     */
    public function users(): Collection;

    /**
     * Momento em que a consulta base foi buscada na API (para "dados de HH:MM").
     */
    public function fetchedAt(LeadFilters $filters): ?CarbonImmutable;

    /**
     * Invalida o cache da consulta (botão "Atualizar agora").
     */
    public function invalidate(LeadFilters $filters): void;
}
