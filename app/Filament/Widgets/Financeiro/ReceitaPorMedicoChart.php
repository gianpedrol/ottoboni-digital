<?php

namespace App\Filament\Widgets\Financeiro;

use App\Support\PeriodoFinanceiro;

class ReceitaPorMedicoChart extends GraficoFinanceiro
{
    protected ?string $heading = 'Receita por médico';

    protected static ?int $sort = 3;

    public function getDescription(): string
    {
        return 'Recebido · ' . PeriodoFinanceiro::rotulo($this->pageFilters);
    }

    protected function getData(): array
    {
        [$de, $ate] = $this->intervalo();
        $receita = $this->relatorio()->receitaPorMedico($de, $ate);

        return [
            'labels' => array_keys($receita),
            'datasets' => [
                [
                    'label' => 'Recebido',
                    'data' => array_values($receita),
                    'backgroundColor' => array_slice(self::CORES, 0, max(1, count($receita))),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function eixoDeValor(): ?string
    {
        return null;
    }
}
