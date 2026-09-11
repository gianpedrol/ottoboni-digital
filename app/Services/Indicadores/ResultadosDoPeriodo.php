<?php

namespace App\Services\Indicadores;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Receivable;
use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\KommoException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Os quatro números do topo do dashboard — leads, consultas, novos contratos
 * e cirurgias — com comparação ao período anterior de mesmo tamanho
 * (mesma regra de docs/metricas.md).
 *
 * Leads vêm do Kommo (created_at). Consultas e cirurgias vêm da agenda do
 * painel (atendimentos realizados) e contratos do financeiro (recebíveis de
 * origem "contrato", pela data de assinatura = created_at). Datas sempre no
 * fuso da clínica.
 */
class ResultadosDoPeriodo
{
    private const TIPOS_CONSULTA = ['consulta', 'retorno', 'online'];

    private const TIPOS_CIRURGIA = ['cirurgia'];

    public function __construct(private readonly LeadRepository $leads) {}

    /**
     * @param  array<int>  $pipelineIds  já restritos ao escopo do usuário
     * @return array{
     *     leads: ?array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>},
     *     consultas: array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>, faltas: int, comparecimento_pct: ?float},
     *     contratos: array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>, valor: float},
     *     cirurgias: array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>, proximas_30d: int}
     * }
     */
    public function calcular(array $pipelineIds, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $tz = (string) config('painel.timezone');
        $de = $de->setTimezone($tz)->startOfDay();
        $ate = $ate->setTimezone($tz)->endOfDay();

        $dias = (int) $de->diffInDays($ate->startOfDay()) + 1;
        $antDe = $de->subDays($dias);
        $antAte = $de->subSecond();

        $doctorIds = $this->doctorIds($pipelineIds);

        $consultas = $this->atendimentos($doctorIds, self::TIPOS_CONSULTA, $de, $ate);
        $realizadas = $consultas->filter(fn (object $a): bool => $a->status === 'realizado');
        $faltas = $consultas->count() - $realizadas->count();

        $cirurgias = $this->atendimentos($doctorIds, self::TIPOS_CIRURGIA, $de, $ate)
            ->filter(fn (object $a): bool => $a->status === 'realizado');

        $contratos = $this->contratos($doctorIds, $de, $ate);

        return [
            'leads' => $this->leadsDoPeriodo($pipelineIds, $de, $ate, $antDe, $antAte),
            'consultas' => [
                ...$this->metrica(
                    $realizadas->count(),
                    $this->atendimentos($doctorIds, self::TIPOS_CONSULTA, $antDe, $antAte)
                        ->filter(fn (object $a): bool => $a->status === 'realizado')->count(),
                    $this->porDia($realizadas->map(fn (object $a): CarbonImmutable => $this->local($a->inicio)), $de, $ate),
                ),
                'faltas' => $faltas,
                'comparecimento_pct' => $consultas->isNotEmpty()
                    ? round($realizadas->count() / $consultas->count() * 100, 1)
                    : null,
            ],
            'contratos' => [
                ...$this->metrica(
                    $contratos->count(),
                    $this->contratos($doctorIds, $antDe, $antAte)->count(),
                    $this->porDia($contratos->map(fn (object $c): CarbonImmutable => $this->local($c->created_at)), $de, $ate),
                ),
                'valor' => (float) $contratos->sum(fn (object $c): float => (float) $c->valor_total),
            ],
            'cirurgias' => [
                ...$this->metrica(
                    $cirurgias->count(),
                    $this->atendimentos($doctorIds, self::TIPOS_CIRURGIA, $antDe, $antAte)
                        ->filter(fn (object $a): bool => $a->status === 'realizado')->count(),
                    $this->porDia($cirurgias->map(fn (object $a): CarbonImmutable => $this->local($a->inicio)), $de, $ate),
                ),
                'proximas_30d' => Appointment::query()
                    ->whereIn('doctor_id', $doctorIds)
                    ->whereIn('tipo', self::TIPOS_CIRURGIA)
                    ->whereIn('status', ['agendado', 'confirmado'])
                    ->entre(CarbonImmutable::now($tz), CarbonImmutable::now($tz)->addDays(30))
                    ->count(),
            ],
        ];
    }

    /**
     * Série semanal (segunda a domingo) das últimas N semanas, para o gráfico
     * de evolução. Leads nulo quando o Kommo não responde.
     *
     * @param  array<int>  $pipelineIds
     * @return array{labels: array<int, string>, leads: ?array<int, int>, consultas: array<int, int>, contratos: array<int, int>, cirurgias: array<int, int>}
     */
    public function evolucaoSemanal(array $pipelineIds, int $semanas = 12): array
    {
        $tz = (string) config('painel.timezone');
        $inicio = CarbonImmutable::now($tz)->startOfWeek(CarbonInterface::MONDAY)->subWeeks($semanas - 1);
        $fim = CarbonImmutable::now($tz)->endOfDay();

        $semanasDoGrafico = [];

        for ($i = 0; $i < $semanas; $i++) {
            $semana = $inicio->addWeeks($i);
            $semanasDoGrafico[$semana->format('Y-m-d')] = $semana->format('d/m');
        }

        $doctorIds = $this->doctorIds($pipelineIds);

        try {
            $leads = $this->porSemana(
                $this->leads->leads(new LeadFilters($pipelineIds, $inicio->utc(), $fim->utc()))
                    ->map(fn (LeadData $l): CarbonImmutable => $l->createdAt->setTimezone($tz)),
                $semanasDoGrafico,
            );
        } catch (KommoException) {
            $leads = null;
        }

        $realizados = fn (array $tipos): Collection => $this->atendimentos($doctorIds, $tipos, $inicio, $fim)
            ->filter(fn (object $a): bool => $a->status === 'realizado')
            ->map(fn (object $a): CarbonImmutable => $this->local($a->inicio));

        return [
            'labels' => array_values($semanasDoGrafico),
            'leads' => $leads,
            'consultas' => $this->porSemana($realizados(self::TIPOS_CONSULTA), $semanasDoGrafico),
            'contratos' => $this->porSemana(
                $this->contratos($doctorIds, $inicio, $fim)->map(fn (object $c): CarbonImmutable => $this->local($c->created_at)),
                $semanasDoGrafico,
            ),
            'cirurgias' => $this->porSemana($realizados(self::TIPOS_CIRURGIA), $semanasDoGrafico),
        ];
    }

    /**
     * @param  array<int>  $pipelineIds
     * @return ?array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>}
     */
    private function leadsDoPeriodo(array $pipelineIds, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $antDe, CarbonImmutable $antAte): ?array
    {
        try {
            // Mesmo recorte do DashboardFiltros: reaproveita o cache da consulta base
            $atual = $this->leads->leads(new LeadFilters($pipelineIds, $de->utc(), $ate->utc()));
            $anterior = $this->leads->leads(new LeadFilters($pipelineIds, $antDe->utc(), $antAte->utc()));
        } catch (KommoException) {
            return null;
        }

        return $this->metrica(
            $atual->count(),
            $anterior->count(),
            $this->porDia($atual->map(fn (LeadData $l): CarbonImmutable => $l->createdAt->setTimezone($de->getTimezone())), $de, $ate),
        );
    }

    /**
     * @param  Collection<int, int>  $doctorIds
     * @param  array<int, string>  $tipos
     * @return Collection<int, stdClass>
     */
    private function atendimentos(Collection $doctorIds, array $tipos, CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        return Appointment::query()
            ->whereIn('doctor_id', $doctorIds)
            ->whereIn('tipo', $tipos)
            ->whereIn('status', ['realizado', 'faltou'])
            ->entre($de, $ate)
            ->toBase()
            ->get(['inicio', 'status']);
    }

    /**
     * @param  Collection<int, int>  $doctorIds
     * @return Collection<int, stdClass>
     */
    private function contratos(Collection $doctorIds, CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        return Receivable::query()
            ->whereIn('doctor_id', $doctorIds)
            ->where('origem', 'contrato')
            ->whereBetween('created_at', [$this->banco($de), $this->banco($ate)])
            ->toBase()
            ->get(['created_at', 'valor_total']);
    }

    /**
     * @param  array<int>  $pipelineIds
     * @return Collection<int, int>
     */
    private function doctorIds(array $pipelineIds): Collection
    {
        return Doctor::query()->whereIn('kommo_pipeline_id', $pipelineIds)->pluck('id');
    }

    /**
     * @param  array<int, int>  $serie
     * @return array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>}
     */
    private function metrica(int $atual, int $anterior, array $serie): array
    {
        return [
            'atual' => $atual,
            'anterior' => $anterior,
            // Sem base (anterior zero) a variação é nula, nunca 100% ou infinito
            'variacao_pct' => $anterior > 0 ? round(($atual - $anterior) / $anterior * 100, 1) : null,
            'serie' => $serie,
        ];
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $datas  já no fuso da clínica
     * @return array<int, int>
     */
    private function porDia(Collection $datas, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $dias = [];

        for ($dia = $de; $dia->lte($ate); $dia = $dia->addDay()) {
            $dias[$dia->format('Y-m-d')] = 0;
        }

        foreach ($datas as $data) {
            $chave = $data->format('Y-m-d');

            if (isset($dias[$chave])) {
                $dias[$chave]++;
            }
        }

        return array_values($dias);
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $datas  já no fuso da clínica
     * @param  array<string, string>  $semanas  segunda-feira (Y-m-d) => rótulo
     * @return array<int, int>
     */
    private function porSemana(Collection $datas, array $semanas): array
    {
        $contagem = array_fill_keys(array_keys($semanas), 0);

        foreach ($datas as $data) {
            $chave = $data->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d');

            if (isset($contagem[$chave])) {
                $contagem[$chave]++;
            }
        }

        return array_values($contagem);
    }

    /**
     * Valor cru do banco (fuso da aplicação) para o fuso da clínica.
     */
    private function local(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, (string) config('app.timezone'))->setTimezone((string) config('painel.timezone'));
    }

    private function banco(CarbonImmutable $data): CarbonImmutable
    {
        return $data->setTimezone((string) config('app.timezone'));
    }
}
