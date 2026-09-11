<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Indicadores\JornadaDoLead;
use App\Services\Kommo\KommoException;
use App\Support\DashboardFiltros;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class FunilJornadaChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Funil: até onde os leads chegaram';

    protected ?string $description = 'Mesmas etapas para todos os médicos.';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $leads = app(LeadRepository::class)->leads(DashboardFiltros::montar($user, $this->pageFilters));
        } catch (KommoException) {
            return ['datasets' => [], 'labels' => []];
        }

        $funil = app(JornadaDoLead::class)->funil($leads);

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_values($funil),
                    'backgroundColor' => ['#f6ddd8', '#f0c2b9', '#e8a497', '#e08777', '#cf6a5b', '#a9483f'],
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_keys($funil),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
