<?php

namespace App\Filament\Widgets\Financeiro;

use App\Services\Financeiro\RelatorioFinanceiro;
use App\Support\Financeiro;
use App\Support\PeriodoFinanceiro;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumoFinanceiroStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Financeiro::podeAcessar();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        [$de, $ate] = PeriodoFinanceiro::intervalo($this->pageFilters);
        $rotulo = mb_strtolower(PeriodoFinanceiro::rotulo($this->pageFilters));

        $resumo = (new RelatorioFinanceiro(PeriodoFinanceiro::medico($this->pageFilters)))->resumo($de, $ate);
        $resultado = $resumo['recebido'] - $resumo['pago'];

        return [
            Stat::make('Recebido', Financeiro::brl($resumo['recebido']))
                ->description($rotulo)
                ->color('success'),
            Stat::make('Pago (despesas)', Financeiro::brl($resumo['pago']))
                ->description($rotulo . ' · todas as contas da clínica')
                ->color('danger'),
            Stat::make('Resultado de caixa', Financeiro::brl($resultado))
                ->description('recebido − pago')
                ->color($resultado >= 0 ? 'success' : 'danger'),
            Stat::make('Em atraso', Financeiro::brl($resumo['em_atraso']))
                ->description('parcelas vencidas e não pagas (total)')
                ->color($resumo['em_atraso'] > 0 ? 'warning' : 'success'),
        ];
    }
}
