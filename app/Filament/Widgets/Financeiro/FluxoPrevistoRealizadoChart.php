<?php

namespace App\Filament\Widgets\Financeiro;

class FluxoPrevistoRealizadoChart extends GraficoFinanceiro
{
    protected ?string $heading = 'Fluxo de caixa: previsto × realizado';

    protected ?string $description = 'Por mês, 6 meses para trás e 3 para frente. Previsto pelo vencimento, realizado pela data do pagamento. O filtro de médico vale para as entradas.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '340px';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $fluxo = $this->relatorio()->fluxoMensal();

        return [
            'labels' => $fluxo['labels'],
            'datasets' => [
                [
                    'label' => 'Entradas previstas',
                    'data' => $fluxo['receita_prevista'],
                    'backgroundColor' => 'rgba(13, 148, 136, 0.25)',
                    'borderColor' => '#d9776d',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Entradas realizadas',
                    'data' => $fluxo['receita_realizada'],
                    'backgroundColor' => '#d9776d',
                    'borderColor' => '#d9776d',
                ],
                [
                    'label' => 'Saídas previstas',
                    'data' => $fluxo['despesa_prevista'],
                    'backgroundColor' => 'rgba(244, 63, 94, 0.2)',
                    'borderColor' => '#a8988b',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Saídas realizadas',
                    'data' => $fluxo['despesa_realizada'],
                    'backgroundColor' => '#a8988b',
                    'borderColor' => '#a8988b',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
