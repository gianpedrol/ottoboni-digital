<?php

namespace App\Filament\Widgets\Financeiro;

use App\Services\Financeiro\RelatorioFinanceiro;
use App\Support\Financeiro;
use App\Support\PeriodoFinanceiro;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Base dos gráficos do Fluxo de caixa: acesso, filtros da página e
 * eixos/tooltip em R$.
 */
abstract class GraficoFinanceiro extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    /** Paleta fixa: as cores se repetem entre gráficos (médico, previsto, realizado). */
    public const CORES = ['#d9776d', '#8fae9f', '#c9a27e', '#7d8fa3', '#c79cae', '#a68a7a', '#b5c99a', '#e6b8a2', '#6d8a7f', '#b07d62'];

    public static function canView(): bool
    {
        return Financeiro::podeAcessar();
    }

    protected function relatorio(): RelatorioFinanceiro
    {
        return new RelatorioFinanceiro(PeriodoFinanceiro::medico($this->pageFilters));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function intervalo(): array
    {
        return PeriodoFinanceiro::intervalo($this->pageFilters);
    }

    protected function getOptions(): RawJs
    {
        $eixo = $this->eixoDeValor();

        if ($eixo === null) {
            return RawJs::make(<<<'JS'
                {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (c) => c.label + ': ' + Number(c.parsed).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
                            },
                        },
                    },
                    scales: { x: { display: false }, y: { display: false } },
                }
            JS);
        }

        $indexAxis = $eixo === 'x' ? 'y' : 'x';

        return RawJs::make(<<<JS
            {
                indexAxis: '{$indexAxis}',
                scales: {
                    {$eixo}: {
                        ticks: {
                            callback: (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 }),
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (c) => c.dataset.label + ': ' + Number(c.parsed.{$eixo}).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
                        },
                    },
                },
            }
        JS);
    }

    /**
     * Eixo que carrega o valor em R$: 'y' (barras verticais), 'x'
     * (horizontais) ou null (pizza/rosca).
     */
    protected function eixoDeValor(): ?string
    {
        return 'y';
    }
}
