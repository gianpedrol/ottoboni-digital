<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\ReceivableInstallment;
use App\Models\User;
use App\Support\SelecaoMedico;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

/**
 * Agenda e financeiro do dia/mês — tabelas locais do painel.
 * No protótipo os dados são de demonstração.
 */
class ClinicaResumoWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Hoje na clínica';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    protected function getDescription(): ?string
    {
        return config('painel.prototipo') ? 'Demonstração · dados fictícios' : null;
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $doctorIds = Doctor::query()
            ->whereIn('kommo_pipeline_id', SelecaoMedico::pipelineIds($user, $this->pageFilters['medico'] ?? null))
            ->pluck('id');

        $hoje = CarbonImmutable::now(config('painel.timezone'));

        $agendaHoje = fn (): Builder => Appointment::query()
            ->whereIn('doctor_id', $doctorIds)
            ->entre($hoje->startOfDay(), $hoje->startOfDay()->addDay())
            ->ocupandoHorario();

        $parcelas = fn (): Builder => ReceivableInstallment::query()
            ->whereHas('receivable', fn (Builder $q) => $q->whereIn('doctor_id', $doctorIds));

        $recebidoMes = (float) $parcelas()
            ->whereBetween('pago_em', [$hoje->startOfMonth()->toDateString(), $hoje->endOfMonth()->toDateString()])
            ->sum('valor_pago');

        $aReceberMes = (float) $parcelas()
            ->whereNull('pago_em')
            ->whereNot('status', 'cancelado')
            ->whereBetween('vencimento', [$hoje->toDateString(), $hoje->endOfMonth()->toDateString()])
            ->sum('valor');

        $emAtraso = fn (): Builder => $parcelas()
            ->whereNull('pago_em')
            ->whereNot('status', 'cancelado')
            ->where('vencimento', '<', $hoje->toDateString());

        return [
            Stat::make('Agendamentos hoje', (string) $agendaHoje()->count())
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description($agendaHoje()->where('status', 'confirmado')->count() . ' confirmados · '
                    . $agendaHoje()->where('status', 'realizado')->count() . ' atendidos'),
            Stat::make('Recebido no mês', $this->dinheiro($recebidoMes))
                ->icon(Heroicon::OutlinedBanknotes)
                ->description('parcelas pagas em ' . $hoje->translatedFormat('F'))
                ->color('success'),
            Stat::make('A receber no mês', $this->dinheiro($aReceberMes))
                ->icon(Heroicon::OutlinedClock)
                ->description('parcelas ainda por vencer'),
            Stat::make('Em atraso', $this->dinheiro((float) $emAtraso()->sum('valor')))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->description($emAtraso()->count() . ' parcelas vencidas')
                ->color('danger'),
        ];
    }

    private function dinheiro(float $valor): string
    {
        return (string) Number::currency($valor, in: 'BRL', locale: 'pt_BR');
    }
}
