<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgendaHojeWidget;
use App\Filament\Widgets\ClinicaResumoWidget;
use App\Filament\Widgets\EvolucaoSemanalChart;
use App\Filament\Widgets\FunilJornadaChart;
use App\Filament\Widgets\OrigemChart;
use App\Filament\Widgets\ResultadosWidget;
use App\Models\User;
use App\Support\PeriodoAtalho;
use App\Support\SelecaoMedico;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static ?string $title = 'Visão geral';

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?int $navigationSort = 1;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return [
            FilterAction::make()
                ->label('Filtros')
                ->schema([
                    Select::make('medico')
                        ->label('Médico')
                        ->options(SelecaoMedico::options($user))
                        ->default('ambos'),
                    Select::make('periodo')
                        ->label('Período')
                        ->options(PeriodoAtalho::options())
                        ->default('30d')
                        ->live(),
                    DatePicker::make('de')
                        ->label('De')
                        ->visible(fn ($get): bool => $get('periodo') === 'personalizado'),
                    DatePicker::make('ate')
                        ->label('Até')
                        ->visible(fn ($get): bool => $get('periodo') === 'personalizado'),
                ]),
        ];
    }

    /**
     * Data de hoje + filtro ativo, para ninguém ler número sem saber o recorte.
     */
    public function getSubheading(): string
    {
        /** @var User $user */
        $user = Auth::user();

        $medicos = SelecaoMedico::options($user);
        $medico = $medicos[$this->filters['medico'] ?? 'ambos'] ?? reset($medicos) ?: 'Todos os médicos';

        [$de, $ate] = PeriodoAtalho::resolver(
            $this->filters['periodo'] ?? '30d',
            $this->filters['de'] ?? null,
            $this->filters['ate'] ?? null,
        );

        $hoje = CarbonImmutable::now(config('painel.timezone'))->locale('pt_BR');

        return ucfirst($hoje->translatedFormat('l, j \d\e F'))
            . " · {$medico} · {$de->format('d/m')} a {$ate->format('d/m/Y')}";
    }

    public function getWidgets(): array
    {
        return [
            ResultadosWidget::class,
            EvolucaoSemanalChart::class,
            ClinicaResumoWidget::class,
            AgendaHojeWidget::class,
            FunilJornadaChart::class,
            OrigemChart::class,
        ];
    }
}
