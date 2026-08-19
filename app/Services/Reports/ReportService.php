<?php

namespace App\Services\Reports;

use App\Enums\FollowupRunStatus;
use App\Models\FollowupRun;
use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\DTO\NoteData;
use App\Services\Kommo\DTO\PipelineData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Gera os 7 blocos do relatório. Regras dos números em docs/metricas.md —
 * qualquer mudança aqui precisa ser refletida lá e nos testes.
 */
class ReportService
{
    public const NAO_INFORMADO = 'Não informado';

    public function __construct(private readonly LeadRepository $repository) {}

    /**
     * @param  array<int>  $pipelineIds  já restritos ao escopo do usuário
     * @param  CarbonImmutable  $fromLocal  início do período no fuso America/Sao_Paulo
     * @param  CarbonImmutable  $toLocal  fim do período no fuso America/Sao_Paulo
     * @param  bool  $deep  liga métricas que exigem chamadas extras (só via job)
     * @return array<string, mixed>
     */
    public function generate(array $pipelineIds, CarbonImmutable $fromLocal, CarbonImmutable $toLocal, bool $deep = false): array
    {
        $tz = config('painel.timezone');

        $fromLocal = $fromLocal->setTimezone($tz)->startOfDay();
        $toLocal = $toLocal->setTimezone($tz)->endOfDay();

        $filters = new LeadFilters(
            pipelineIds: $pipelineIds,
            from: $fromLocal->utc(),
            to: $toLocal->utc(),
        );

        $leads = $this->repository->leads($filters);

        // Período anterior com a MESMA quantidade de dias, imediatamente antes.
        $dias = (int) $fromLocal->diffInDays($toLocal->addSecond());
        $anteriorFrom = $fromLocal->subDays($dias);
        $anteriorTo = $fromLocal->subSecond();

        $leadsAnterior = $this->repository->leads(
            $filters->comPeriodo($anteriorFrom->utc(), $anteriorTo->utc()),
        );

        return [
            'periodo' => [
                'de' => $fromLocal->toIso8601String(),
                'ate' => $toLocal->toIso8601String(),
                'dias' => $dias,
                'pipeline_ids' => $pipelineIds,
            ],
            'volume' => $this->blocoVolume($leads, $leadsAnterior, $fromLocal, $toLocal, $tz),
            'origem' => $this->blocoDistribuicao($leads, fn (LeadData $l): ?string => $l->origem),
            'temperatura' => $this->blocoDistribuicao($leads, fn (LeadData $l): ?string => $l->temperatura),
            'procedimentos' => $this->blocoProcedimentos($leads),
            'funil' => $this->blocoFunil($leads, $pipelineIds),
            'followup' => $this->blocoFollowup($pipelineIds, $fromLocal->utc(), $toLocal->utc()),
            'produtividade' => $this->blocoProdutividade($leads, $deep),
            'gerado_em' => now()->setTimezone($tz)->toIso8601String(),
            'modo_profundo' => $deep,
        ];
    }

    /**
     * Bloco 1 — volume por dia e comparação com o período anterior.
     *
     * @param  Collection<int, LeadData>  $leads
     * @param  Collection<int, LeadData>  $anterior
     * @return array<string, mixed>
     */
    private function blocoVolume(Collection $leads, Collection $anterior, CarbonImmutable $from, CarbonImmutable $to, string $tz): array
    {
        $porDia = [];

        for ($dia = $from; $dia->lte($to); $dia = $dia->addDay()) {
            $porDia[$dia->format('Y-m-d')] = 0;
        }

        foreach ($leads as $lead) {
            $chave = $lead->createdAt->setTimezone($tz)->format('Y-m-d');

            if (array_key_exists($chave, $porDia)) {
                $porDia[$chave]++;
            }
        }

        $total = $leads->count();
        $totalAnterior = $anterior->count();

        return [
            'total' => $total,
            'por_dia' => $porDia,
            'total_periodo_anterior' => $totalAnterior,
            'variacao_pct' => $totalAnterior > 0
                ? round((($total - $totalAnterior) / $totalAnterior) * 100, 1)
                : null,
        ];
    }

    /**
     * Blocos 2 e 3 — distribuição por um campo, com "Não informado" sempre
     * dentro do total (a soma das fatias TEM que bater com o bloco 1).
     *
     * @param  Collection<int, LeadData>  $leads
     * @return array<string, mixed>
     */
    private function blocoDistribuicao(Collection $leads, callable $campo): array
    {
        $total = $leads->count();

        $fatias = $leads
            ->groupBy(fn (LeadData $l): string => trim((string) $campo($l)) ?: self::NAO_INFORMADO)
            ->map(fn (Collection $grupo): int => $grupo->count())
            ->sortDesc();

        return [
            'total' => $total,
            'fatias' => $fatias
                ->map(fn (int $qtd): array => [
                    'total' => $qtd,
                    'pct' => $total > 0 ? round($qtd / $total * 100, 1) : 0.0,
                ])
                ->toArray(),
        ];
    }

    /**
     * Bloco 4 — ranking de procedimentos: top 10 + "Outros".
     *
     * @param  Collection<int, LeadData>  $leads
     * @return array<string, mixed>
     */
    private function blocoProcedimentos(Collection $leads): array
    {
        $total = $leads->count();

        $contagem = $leads
            ->groupBy(fn (LeadData $l): string => trim((string) $l->procedimento) ?: self::NAO_INFORMADO)
            ->map(fn (Collection $grupo): int => $grupo->count())
            ->sortDesc();

        $top = $contagem->take(10);
        $outros = $contagem->skip(10)->sum();

        $fatias = $top->toArray();

        if ($outros > 0) {
            $fatias['Outros'] = $outros;
        }

        return [
            'total' => $total,
            'ranking' => collect($fatias)
                ->map(fn (int $qtd): array => [
                    'total' => $qtd,
                    'pct' => $total > 0 ? round($qtd / $total * 100, 1) : 0.0,
                ])
                ->toArray(),
        ];
    }

    /**
     * Bloco 5 — funil por etapa, um funil por médico selecionado.
     *
     * Taxa de passagem usa o dado ao vivo: "chegou à etapa X" = está em X,
     * numa etapa posterior, ou ganhou. Leads perdidos contam apenas na
     * primeira etapa, porque a API não informa em qual etapa se perdeu.
     *
     * @param  Collection<int, LeadData>  $leads
     * @param  array<int>  $pipelineIds
     * @return array<string, mixed>
     */
    private function blocoFunil(Collection $leads, array $pipelineIds): array
    {
        $ganhoId = (int) config('kommo.status_ganho');
        $perdidoId = (int) config('kommo.status_perdido');

        $funis = [];

        foreach ($pipelineIds as $pipelineId) {
            /** @var PipelineData|null $pipeline */
            $pipeline = $this->repository->pipelines()->first(
                fn (PipelineData $p): bool => $p->id === $pipelineId,
            );

            if ($pipeline === null) {
                continue;
            }

            $doPipeline = $leads->filter(fn (LeadData $l): bool => $l->pipelineId === $pipelineId);
            $total = $doPipeline->count();
            $ganhos = $doPipeline->filter(fn (LeadData $l): bool => $l->statusId === $ganhoId)->count();
            $perdidos = $doPipeline->filter(fn (LeadData $l): bool => $l->statusId === $perdidoId)->count();

            $etapasAtivas = array_values(array_filter(
                $pipeline->statuses,
                fn ($s): bool => ! in_array($s->id, [$ganhoId, $perdidoId], true),
            ));

            $ordemPorStatus = [];

            foreach ($etapasAtivas as $indice => $status) {
                $ordemPorStatus[$status->id] = $indice;
            }

            $etapas = [];
            $chegaramAnterior = null;

            foreach ($etapasAtivas as $indice => $status) {
                $atual = $doPipeline
                    ->filter(fn (LeadData $l): bool => $l->statusId === $status->id)
                    ->count();

                $chegaram = $doPipeline
                    ->filter(function (LeadData $l) use ($ordemPorStatus, $indice, $ganhoId, $perdidoId): bool {
                        if ($l->statusId === $ganhoId) {
                            return true;
                        }

                        if ($l->statusId === $perdidoId) {
                            return $indice === 0;
                        }

                        return ($ordemPorStatus[$l->statusId] ?? -1) >= $indice;
                    })
                    ->count();

                $etapas[] = [
                    'status_id' => $status->id,
                    'nome' => $status->name,
                    'atual' => $atual,
                    'chegaram' => $chegaram,
                    'taxa_passagem_pct' => ($chegaramAnterior !== null && $chegaramAnterior > 0)
                        ? round($chegaram / $chegaramAnterior * 100, 1)
                        : null,
                ];

                $chegaramAnterior = $chegaram;
            }

            $funis[] = [
                'pipeline_id' => $pipelineId,
                'nome' => $pipeline->name,
                'total' => $total,
                'ganhos' => $ganhos,
                'perdidos' => $perdidos,
                'taxa_ganho_pct' => $total > 0 ? round($ganhos / $total * 100, 1) : 0.0,
                'etapas' => $etapas,
            ];
        }

        return ['funis' => $funis];
    }

    /**
     * Bloco 6 — follow-up (dados locais do painel).
     *
     * "Reativados" exige histórico de conversa que só existirá na Fase 3;
     * até lá o campo fica null de propósito, nunca zero enganoso.
     *
     * @param  array<int>  $pipelineIds
     * @return array<string, mixed>
     */
    private function blocoFollowup(array $pipelineIds, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $runs = FollowupRun::query()
            ->whereHas('doctor', fn ($q) => $q->whereIn('kommo_pipeline_id', $pipelineIds))
            ->whereBetween('executado_em', [$fromUtc, $toUtc])
            ->get();

        $enviados = $runs->whereIn('status', [FollowupRunStatus::Enviado, FollowupRunStatus::Respondido]);
        $falharam = $runs->where('status', FollowupRunStatus::Falhou);

        $respondidos72h = $enviados
            ->filter(function (FollowupRun $run): bool {
                return $run->status === FollowupRunStatus::Respondido
                    && $run->executado_em !== null
                    && $run->updated_at->diffInHours($run->executado_em, true) <= 72;
            })
            ->count();

        $tentativas = $enviados->count() + $falharam->count();

        return [
            'enviados' => $enviados->count(),
            'por_canal' => $enviados
                ->groupBy(fn (FollowupRun $r): string => $r->canal_usado?->value ?? 'desconhecido')
                ->map(fn ($grupo): int => $grupo->count())
                ->toArray(),
            'taxa_entrega_pct' => $tentativas > 0
                ? round($enviados->count() / $tentativas * 100, 1)
                : null,
            'respondidos_72h' => $respondidos72h,
            'taxa_resposta_pct' => $enviados->count() > 0
                ? round($respondidos72h / $enviados->count() * 100, 1)
                : null,
            'reativados' => null,
        ];
    }

    /**
     * Bloco 7 — produtividade. O tempo até a primeira nota humana exige uma
     * chamada de notas por lead, então só roda no modo profundo (via job).
     *
     * @param  Collection<int, LeadData>  $leads
     * @return array<string, mixed>
     */
    private function blocoProdutividade(Collection $leads, bool $deep): array
    {
        $nomes = $this->repository->users()->keyBy('id');

        $porResponsavel = $leads
            ->groupBy(fn (LeadData $l): string => $nomes->get($l->responsibleUserId)?->name
                ?? ($l->responsibleUserId ? "Usuário {$l->responsibleUserId}" : self::NAO_INFORMADO))
            ->map(fn (Collection $grupo): int => $grupo->count())
            ->sortDesc()
            ->toArray();

        $tempoPrimeiraNota = null;

        if ($deep && $leads->isNotEmpty()) {
            $horas = [];

            foreach ($leads as $lead) {
                /** @var NoteData|null $primeiraHumana */
                $primeiraHumana = $this->repository->notes($lead->id)
                    ->filter(fn (NoteData $n): bool => $n->humana())
                    ->sortBy(fn (NoteData $n) => $n->createdAt->getTimestamp())
                    ->first();

                if ($primeiraHumana !== null) {
                    $horas[] = $lead->createdAt->diffInHours($primeiraHumana->createdAt, true);
                }
            }

            $tempoPrimeiraNota = $horas === [] ? null : round(array_sum($horas) / count($horas), 1);
        }

        return [
            'por_responsavel' => $porResponsavel,
            'tempo_medio_primeira_nota_horas' => $tempoPrimeiraNota,
            'observacao' => $deep
                ? null
                : 'Tempo até a primeira nota humana só é calculado no relatório gerado em segundo plano.',
        ];
    }
}
