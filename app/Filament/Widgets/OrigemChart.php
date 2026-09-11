<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\KommoException;
use App\Support\DashboardFiltros;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class OrigemChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Origem dos leads';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $filters = DashboardFiltros::montar($user, $this->pageFilters);

        try {
            $leads = app(LeadRepository::class)->leads($filters);
        } catch (KommoException) {
            return ['datasets' => [], 'labels' => []];
        }

        $fatias = $leads
            ->groupBy(fn (LeadData $l): string => trim((string) $l->origem) ?: 'Não informado')
            ->map(fn ($grupo): int => $grupo->count())
            ->sortDesc();

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $fatias->values()->all(),
                ],
            ],
            'labels' => $fatias->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
