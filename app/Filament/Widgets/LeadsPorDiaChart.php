<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Kommo\KommoException;
use App\Support\DashboardFiltros;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class LeadsPorDiaChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Leads por dia';

    protected int|string|array $columnSpan = 'full';

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

        $tz = config('painel.timezone');

        $porDia = [];

        $de = $filters->from->setTimezone($tz)->startOfDay();
        $ate = $filters->to->setTimezone($tz);

        for ($dia = $de; $dia->lte($ate); $dia = $dia->addDay()) {
            $porDia[$dia->format('d/m')] = 0;
        }

        foreach ($leads as $lead) {
            $chave = $lead->createdAt->setTimezone($tz)->format('d/m');

            if (array_key_exists($chave, $porDia)) {
                $porDia[$chave]++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_values($porDia),
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_keys($porDia),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
