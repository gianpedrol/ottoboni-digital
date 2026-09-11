<?php

namespace App\Filament\Resources\BankTransactions\Widgets;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Support\Financeiro;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BankAccountsStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Financeiro::podeAcessar();
    }

    protected function getStats(): array
    {
        $stats = [];

        foreach (BankAccount::query()->orderBy('id')->get() as $conta) {
            $pendentes = $conta->transactions()->naoConciliados()->count();

            $stats[] = Stat::make("Saldo · {$conta->apelido}", Financeiro::brl($conta->saldoAtual()))
                ->description($conta->banco . ($conta->conta ? " · c/c {$conta->conta}" : '')
                    . ($pendentes > 0 ? " · {$pendentes} a conciliar" : ''))
                ->color('primary');
        }

        $pendentes = BankTransaction::query()->naoConciliados();

        $stats[] = Stat::make('Não conciliados', (string) (clone $pendentes)->count())
            ->description(Financeiro::brl((clone $pendentes)->where('valor', '>', 0)->sum('valor')) . ' em créditos · '
                . Financeiro::brl(abs((float) (clone $pendentes)->where('valor', '<', 0)->sum('valor'))) . ' em débitos')
            ->color((clone $pendentes)->exists() ? 'warning' : 'success');

        return $stats;
    }
}
