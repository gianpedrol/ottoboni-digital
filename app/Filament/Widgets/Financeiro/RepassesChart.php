<?php

namespace App\Filament\Widgets\Financeiro;

use App\Support\PeriodoFinanceiro;

class RepassesChart extends GraficoFinanceiro
{
    protected ?string $heading = 'Repasses aos médicos';

    protected static ?int $sort = 5;

    public function getDescription(): string
    {
        return 'Recebido × % de repasse de cada serviço · ' . PeriodoFinanceiro::rotulo($this->pageFilters);
    }

    protected function getData(): array
    {
        [$de, $ate] = $this->intervalo();
        $repasses = $this->relatorio()->repasses($de, $ate);

        return [
            'labels' => array_keys($repasses),
            'datasets' => [
                [
                    'label' => 'Recebido',
                    'data' => array_column($repasses, 'receita'),
                    'backgroundColor' => 'rgba(13, 148, 136, 0.3)',
                    'borderColor' => '#d9776d',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Repasse',
                    'data' => array_column($repasses, 'repasse'),
                    'backgroundColor' => '#c9a27e',
                    'borderColor' => '#c9a27e',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
