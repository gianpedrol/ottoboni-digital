<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Indicadores\ResultadosDoPeriodo;
use App\Support\SelecaoMedico;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * Leads, consultas, contratos e cirurgias por semana. Dois eixos: leads são
 * uma ordem de grandeza maiores e achatariam as outras linhas.
 */
class EvolucaoSemanalChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Evolução semanal';

    protected ?string $description = 'Últimas 12 semanas · leads no eixo da esquerda, os demais no da direita.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $evolucao = app(ResultadosDoPeriodo::class)->evolucaoSemanal(
            SelecaoMedico::pipelineIds($user, $this->pageFilters['medico'] ?? null),
        );

        $datasets = [];

        if ($evolucao['leads'] !== null) {
            $datasets[] = [
                'type' => 'bar',
                'label' => 'Leads',
                'data' => $evolucao['leads'],
                'backgroundColor' => '#f3d3cc',
                'borderRadius' => 4,
                'yAxisID' => 'y',
                'order' => 2,
            ];
        }

        foreach ([
            ['Consultas', 'consultas', '#6d8a7f'],
            ['Novos contratos', 'contratos', '#d9776d'],
            ['Cirurgias', 'cirurgias', '#7d8fa3'],
        ] as [$rotulo, $chave, $cor]) {
            $datasets[] = [
                'type' => 'line',
                'label' => $rotulo,
                'data' => $evolucao[$chave],
                'borderColor' => $cor,
                'backgroundColor' => $cor,
                'tension' => 0.35,
                'pointRadius' => 3,
                'yAxisID' => 'y1',
                'order' => 1,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $evolucao['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'boxWidth' => 8],
                ],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Leads'],
                    'grid' => ['color' => 'rgba(120, 100, 90, 0.08)'],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Consultas · contratos · cirurgias'],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
