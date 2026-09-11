<?php

namespace App\Services\Financeiro;

use App\Enums\StatusFinanceiro;
use App\Models\Payable;
use App\Models\ReceivableInstallment;
use App\Support\Financeiro;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Números do Fluxo de caixa. O filtro de médico vale para as receitas
 * (contas a pagar são da clínica, sem médico).
 */
class RelatorioFinanceiro
{
    public function __construct(private readonly ?int $doctorId = null) {}

    /**
     * Previsto × realizado por mês, de N meses atrás até M meses à frente.
     *
     * @return array{labels: array<int, string>, receita_prevista: array<int, float>, receita_realizada: array<int, float>, despesa_prevista: array<int, float>, despesa_realizada: array<int, float>}
     */
    public function fluxoMensal(int $mesesAntes = 6, int $mesesDepois = 3): array
    {
        $hoje = Financeiro::hoje();
        $inicio = $hoje->startOfMonth()->subMonthsNoOverflow($mesesAntes);
        $fim = $hoje->startOfMonth()->addMonthsNoOverflow($mesesDepois)->endOfMonth();

        $meses = [];

        for ($mes = $inicio; $mes->lte($fim); $mes = $mes->addMonthNoOverflow()) {
            $meses[$mes->format('Y-m')] = ucfirst($mes->translatedFormat('M/y'));
        }

        $porMes = fn (Collection $itens, string $campoData, callable $valor): array => array_map(
            fn (string $chave): float => round((float) $itens
                ->filter(fn ($item): bool => $item->{$campoData}?->format('Y-m') === $chave)
                ->sum($valor), 2),
            array_keys($meses),
        );

        $previstas = $this->parcelas()
            ->where('status', '!=', StatusFinanceiro::Cancelado->value)
            ->whereBetween('vencimento', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['vencimento', 'valor']);

        $recebidas = $this->parcelas()->pagas()
            ->whereBetween('pago_em', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['pago_em', 'valor', 'valor_pago']);

        $contas = Payable::query()
            ->where('status', '!=', StatusFinanceiro::Cancelado->value)
            ->whereBetween('vencimento', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['vencimento', 'valor']);

        $pagas = Payable::query()->pagas()
            ->whereBetween('pago_em', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['pago_em', 'valor']);

        return [
            'labels' => array_values($meses),
            'receita_prevista' => $porMes($previstas, 'vencimento', fn ($p): float => (float) $p->valor),
            'receita_realizada' => $porMes($recebidas, 'pago_em', fn ($p): float => (float) ($p->valor_pago ?? $p->valor)),
            'despesa_prevista' => $porMes($contas, 'vencimento', fn ($c): float => (float) $c->valor),
            'despesa_realizada' => $porMes($pagas, 'pago_em', fn ($c): float => (float) $c->valor),
        ];
    }

    /**
     * @return array<string, float> nome do médico => recebido no período
     */
    public function receitaPorMedico(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        return $this->recebidasNoPeriodo($de, $ate)
            ->groupBy(fn (ReceivableInstallment $p): string => $p->receivable->doctor->nome ?? 'Sem médico')
            ->map(fn (Collection $grupo): float => round((float) $grupo->sum(fn (ReceivableInstallment $p): float => $this->pago($p)), 2))
            ->sortDesc()
            ->all();
    }

    /**
     * @return array<string, float> serviço => recebido no período (os N maiores)
     */
    public function receitaPorServico(CarbonImmutable $de, CarbonImmutable $ate, int $limite = 10): array
    {
        return $this->recebidasNoPeriodo($de, $ate)
            ->groupBy(fn (ReceivableInstallment $p): string => $p->receivable->service->nome ?? 'Outros (sem serviço)')
            ->map(fn (Collection $grupo): float => round((float) $grupo->sum(fn (ReceivableInstallment $p): float => $this->pago($p)), 2))
            ->sortDesc()
            ->take($limite)
            ->all();
    }

    /**
     * Repasse = valor recebido × % de repasse do serviço.
     *
     * @return array<string, array{receita: float, repasse: float}>
     */
    public function repasses(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        return $this->recebidasNoPeriodo($de, $ate)
            ->groupBy(fn (ReceivableInstallment $p): string => $p->receivable->doctor->nome ?? 'Sem médico')
            ->map(fn (Collection $grupo): array => [
                'receita' => round((float) $grupo->sum(fn (ReceivableInstallment $p): float => $this->pago($p)), 2),
                'repasse' => round((float) $grupo->sum(fn (ReceivableInstallment $p): float => $this->pago($p)
                    * (float) ($p->receivable->service->repasse_pct ?? 0) / 100), 2),
            ])
            ->all();
    }

    /**
     * Valor em atraso por faixa de dias desde o vencimento.
     *
     * @return array<string, float>
     */
    public function inadimplencia(): array
    {
        $hoje = Financeiro::hoje();

        $faixas = ['Até 30 dias' => 0.0, '31 a 60 dias' => 0.0, 'Mais de 60 dias' => 0.0];

        foreach ($this->parcelas()->atrasadas()->get(['vencimento', 'valor']) as $parcela) {
            $dias = (int) $parcela->vencimento->diffInDays($hoje);

            $faixa = match (true) {
                $dias <= 30 => 'Até 30 dias',
                $dias <= 60 => '31 a 60 dias',
                default => 'Mais de 60 dias',
            };

            $faixas[$faixa] += (float) $parcela->valor;
        }

        return array_map(fn (float $v): float => round($v, 2), $faixas);
    }

    /**
     * @return array{recebido: float, pago: float, a_receber: float, em_atraso: float}
     */
    public function resumo(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $periodo = [$de->toDateString(), $ate->toDateString()];

        return [
            'recebido' => round((float) $this->recebidasNoPeriodo($de, $ate)->sum(fn (ReceivableInstallment $p): float => $this->pago($p)), 2),
            'pago' => round((float) Payable::query()->pagas()->whereBetween('pago_em', $periodo)->sum('valor'), 2),
            'a_receber' => round((float) $this->parcelas()->emAberto()->whereBetween('vencimento', $periodo)->sum('valor'), 2),
            'em_atraso' => round((float) $this->parcelas()->atrasadas()->sum('valor'), 2),
        ];
    }

    /**
     * @return Builder<ReceivableInstallment>
     */
    private function parcelas(): Builder
    {
        return ReceivableInstallment::query()
            ->when($this->doctorId, fn (Builder $q, int $id) => $q->whereHas('receivable', fn (Builder $r) => $r->where('doctor_id', $id)));
    }

    /**
     * @return Collection<int, ReceivableInstallment>
     */
    private function recebidasNoPeriodo(CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        return $this->parcelas()
            ->pagas()
            ->whereBetween('pago_em', [$de->toDateString(), $ate->toDateString()])
            ->with(['receivable.doctor', 'receivable.service'])
            ->get();
    }

    private function pago(ReceivableInstallment $parcela): float
    {
        return (float) ($parcela->valor_pago ?? $parcela->valor);
    }
}
