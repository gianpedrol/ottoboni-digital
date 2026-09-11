<?php

namespace App\Filament\Widgets\Financeiro;

use App\Support\PeriodoFinanceiro;

class ReceitaPorServicoChart extends GraficoFinanceiro
{
    protected ?string $heading = 'Receita por serviço (top 10)';

    protected static ?int $sort = 4;

    public function getDescription(): string
    {
        return 'Recebido · ' . PeriodoFinanceiro::rotulo($this->pageFilters);
    }

    protected function getData(): array
    {
        [$de, $ate] = $this->intervalo();
        $receita = $this->relatorio()->receitaPorServico($de, $ate);

        return [
            'labels' => array_keys($receita),
            'datasets' => [
                [
                    'label' => 'Recebido',
                    'data' => array_values($receita),
                    'backgroundColor' => '#d9776d',
                    'borderColor' => '#d9776d',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function eixoDeValor(): ?string
    {
        return 'x';
    }
}
