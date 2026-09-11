<?php

namespace App\Filament\Widgets\Financeiro;

class InadimplenciaChart extends GraficoFinanceiro
{
    protected ?string $heading = 'Inadimplência por faixa de atraso';

    protected ?string $description = 'Parcelas vencidas e não pagas, pelo número de dias desde o vencimento.';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $faixas = $this->relatorio()->inadimplencia();

        return [
            'labels' => array_keys($faixas),
            'datasets' => [
                [
                    'label' => 'Em atraso',
                    'data' => array_values($faixas),
                    'backgroundColor' => ['#e8c3a8', '#e07a70', '#a9483f'],
                    'borderColor' => ['#e8c3a8', '#e07a70', '#a9483f'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
