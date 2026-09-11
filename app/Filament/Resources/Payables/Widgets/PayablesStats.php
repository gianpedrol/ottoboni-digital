<?php

namespace App\Filament\Resources\Payables\Widgets;

use App\Models\Payable;
use App\Support\Financeiro;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PayablesStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Financeiro::podeAcessar();
    }

    protected function getStats(): array
    {
        $hoje = Financeiro::hoje();
        $mes = [$hoje->startOfMonth()->toDateString(), $hoje->endOfMonth()->toDateString()];

        $aPagar = Payable::query()->emAberto()->whereBetween('vencimento', $mes);
        $pago = Payable::query()->pagas()->whereBetween('pago_em', $mes);
        $vencidas = Payable::query()->atrasadas();

        $qtdVencidas = (clone $vencidas)->count();

        return [
            Stat::make('A pagar no mês', Financeiro::brl((clone $aPagar)->sum('valor')))
                ->description((clone $aPagar)->count() . ' contas em aberto com vencimento em ' . $hoje->translatedFormat('F'))
                ->color('warning'),
            Stat::make('Pago no mês', Financeiro::brl((clone $pago)->sum('valor')))
                ->description((clone $pago)->count() . ' contas pagas')
                ->color('success'),
            Stat::make('Vencidas', Financeiro::brl((clone $vencidas)->sum('valor')))
                ->description($qtdVencidas . ' contas vencidas e não pagas')
                ->color($qtdVencidas > 0 ? 'danger' : 'success'),
        ];
    }
}
