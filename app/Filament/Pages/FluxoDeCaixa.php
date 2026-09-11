<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Widgets\Financeiro\FluxoPrevistoRealizadoChart;
use App\Filament\Widgets\Financeiro\InadimplenciaChart;
use App\Filament\Widgets\Financeiro\ReceitaPorMedicoChart;
use App\Filament\Widgets\Financeiro\ReceitaPorServicoChart;
use App\Filament\Widgets\Financeiro\RepassesChart;
use App\Filament\Widgets\Financeiro\ResumoFinanceiroStats;
use App\Models\Doctor;
use App\Support\Financeiro;
use App\Support\PeriodoFinanceiro;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Relatórios do Financeiro em gráficos. Reaproveita a página de dashboard
 * do Filament (grade de widgets + formulário de filtros).
 */
class FluxoDeCaixa extends Dashboard
{
    use ComSeloDemonstracao;
    use HasFiltersAction;

    protected static string $routePath = 'fluxo-de-caixa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Fluxo de caixa';

    protected static ?string $navigationLabel = 'Fluxo de caixa';

    public static function canAccess(): bool
    {
        return Financeiro::podeAcessar();
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::$routePath;
    }

    /**
     * Mesmo padrão do dashboard: filtros num painel lateral, e o recorte
     * ativo aparece na descrição de cada gráfico.
     */
    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filtros')
                ->schema([
                    Select::make('medico')
                        ->label('Médico')
                        ->options(fn (): array => Doctor::query()->orderBy('nome')->pluck('nome', 'id')->all())
                        ->placeholder('Todos os médicos'),
                    Select::make('periodo')
                        ->label('Período')
                        ->helperText('Vale para receita e repasses.')
                        ->options(PeriodoFinanceiro::opcoes())
                        ->default('mes')
                        ->selectablePlaceholder(false),
                ]),
        ];
    }

    public function getWidgets(): array
    {
        return [
            ResumoFinanceiroStats::class,
            FluxoPrevistoRealizadoChart::class,
            ReceitaPorMedicoChart::class,
            ReceitaPorServicoChart::class,
            RepassesChart::class,
            InadimplenciaChart::class,
        ];
    }
}
