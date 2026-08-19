<?php

namespace App\Repositories;

use App\Services\Kommo\DTO\LeadData;
use Carbon\CarbonImmutable;

/**
 * Filtros normalizados da consulta de leads.
 *
 * A parte "base" (pipelines + período + busca) vai para a API do Kommo e
 * define a chave de cache. Os filtros de campo personalizado (origem,
 * temperatura, procedimento, etapa, gerado pela agente) são aplicados em
 * memória sobre o resultado cacheado — trocar temperatura não dispara
 * novas chamadas à API.
 */
final readonly class LeadFilters
{
    /**
     * @param  array<int>  $pipelineIds  já restritos ao escopo do usuário
     */
    public function __construct(
        public array $pipelineIds,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $search = null,
        public ?string $origem = null,
        public ?string $temperatura = null,
        public ?string $procedimento = null,
        public ?int $statusId = null,
        public ?bool $geradoPelaAgente = null,
    ) {}

    /**
     * Chave de cache da consulta base (o que realmente vai à API).
     */
    public function baseKey(): string
    {
        $pipelines = $this->pipelineIds;
        sort($pipelines);

        return 'kommo:leads:' . md5(implode('|', [
            implode(',', $pipelines),
            $this->from->getTimestamp(),
            $this->to->getTimestamp(),
            mb_strtolower(trim((string) $this->search)),
        ]));
    }

    /**
     * Query string para GET /leads. Datas já em timestamp Unix (UTC).
     *
     * @return array<string, mixed>
     */
    public function toApiQuery(): array
    {
        $query = [
            'filter' => [
                'pipeline_id' => $this->pipelineIds,
                'created_at' => [
                    'from' => $this->from->getTimestamp(),
                    'to' => $this->to->getTimestamp(),
                ],
            ],
            'with' => 'contacts,source,loss_reason',
            'order' => ['created_at' => 'desc'],
        ];

        if (filled($this->search)) {
            $query['query'] = trim((string) $this->search);
        }

        return $query;
    }

    /**
     * Filtros em memória (aplicados sobre o cache da consulta base).
     */
    public function matches(LeadData $lead): bool
    {
        if ($this->origem !== null && ! $this->sameValue($lead->origem, $this->origem)) {
            return false;
        }

        if ($this->temperatura !== null && ! $this->sameValue($lead->temperatura, $this->temperatura)) {
            return false;
        }

        if ($this->procedimento !== null && ! $this->sameValue($lead->procedimento, $this->procedimento)) {
            return false;
        }

        if ($this->statusId !== null && $lead->statusId !== $this->statusId) {
            return false;
        }

        if ($this->geradoPelaAgente !== null && $lead->geradoPelaAgente() !== $this->geradoPelaAgente) {
            return false;
        }

        return true;
    }

    public function comPeriodo(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self(
            pipelineIds: $this->pipelineIds,
            from: $from,
            to: $to,
            search: $this->search,
            origem: $this->origem,
            temperatura: $this->temperatura,
            procedimento: $this->procedimento,
            statusId: $this->statusId,
            geradoPelaAgente: $this->geradoPelaAgente,
        );
    }

    private function sameValue(?string $leadValue, string $filterValue): bool
    {
        return mb_strtolower(trim((string) $leadValue)) === mb_strtolower(trim($filterValue));
    }
}
