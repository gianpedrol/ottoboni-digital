<?php

namespace App\Filament\Widgets;

use App\Enums\FollowupRunStatus;
use App\Models\FollowupRun;
use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\KommoException;
use App\Support\DashboardFiltros;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class IndicadoresWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $filters = DashboardFiltros::montar($user, $this->pageFilters);

        try {
            $leads = app(LeadRepository::class)->leads($filters);
        } catch (KommoException) {
            return [
                Stat::make('Kommo indisponível', '—')
                    ->description('Verifique o token em Configurações.')
                    ->color('danger'),
            ];
        }

        $total = $leads->count();

        $quentes = $leads
            ->filter(fn (LeadData $l): bool => mb_strtolower((string) $l->temperatura) === 'quente')
            ->count();

        $ganhoId = (int) config('kommo.status_ganho');
        $perdidoId = (int) config('kommo.status_perdido');

        $semResposta48h = $leads
            ->filter(fn (LeadData $l): bool => ! in_array($l->statusId, [$ganhoId, $perdidoId], true)
                && $l->updatedAt !== null
                && $l->updatedAt->lt(now()->subHours(48)))
            ->count();

        $runs = FollowupRun::query()
            ->whereHas('doctor', fn ($q) => $q->whereIn('kommo_pipeline_id', $filters->pipelineIds))
            ->whereBetween('executado_em', [$filters->from, $filters->to])
            ->get();

        $enviados = $runs->whereIn('status', [FollowupRunStatus::Enviado, FollowupRunStatus::Respondido])->count();
        $respondidos = $runs->where('status', FollowupRunStatus::Respondido)->count();

        return [
            Stat::make('Leads novos', (string) $total),
            Stat::make('% quentes', $total > 0 ? round($quentes / $total * 100) . '%' : '—')
                ->description("{$quentes} de {$total}")
                ->color('danger'),
            Stat::make('Follow-ups enviados', (string) $enviados)
                ->description('no período'),
            Stat::make('Follow-ups respondidos', (string) $respondidos)
                ->color('success'),
            Stat::make('Sem resposta há 48h+', (string) $semResposta48h)
                ->description('leads em aberto sem atividade')
                ->color($semResposta48h > 0 ? 'warning' : 'success'),
        ];
    }
}
