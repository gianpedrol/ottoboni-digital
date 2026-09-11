<?php

namespace App\Filament\Resources\Receivables\Widgets;

use App\Models\ReceivableInstallment;
use App\Support\Financeiro;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReceivablesStats extends StatsOverviewWidget
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

        $aReceber = ReceivableInstallment::query()->emAberto()->whereBetween('vencimento', $mes);
        $recebido = ReceivableInstallment::query()->pagas()->whereBetween('pago_em', $mes);
        $atrasadas = ReceivableInstallment::query()->atrasadas();

        $qtdAtrasadas = (clone $atrasadas)->count();

        return [
            Stat::make('A receber no mês', Financeiro::brl((clone $aReceber)->sum('valor')))
                ->description((clone $aReceber)->count() . ' parcelas em aberto com vencimento em ' . $hoje->translatedFormat('F'))
                ->color('warning'),
            Stat::make('Recebido no mês', Financeiro::brl((clone $recebido)->sum('valor_pago')))
                ->description((clone $recebido)->count() . ' parcelas pagas')
                ->color('success'),
            Stat::make('Em atraso', Financeiro::brl((clone $atrasadas)->sum('valor')))
                ->description($qtdAtrasadas . ' parcelas vencidas e não pagas')
                ->color($qtdAtrasadas > 0 ? 'danger' : 'success'),
        ];
    }
}
