<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\IndicadoresWidget;
use App\Filament\Widgets\LeadsPorDiaChart;
use App\Filament\Widgets\OrigemChart;
use App\Models\User;
use App\Support\PeriodoAtalho;
use App\Support\SelecaoMedico;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Visão geral';

    protected static string|UnitEnum|null $navigationGroup = 'Atendimento';

    protected static ?int $navigationSort = 1;

    public function filtersForm(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema
            ->components([
                Section::make()
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
                    ])
                    ->columns(4),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            IndicadoresWidget::class,
            LeadsPorDiaChart::class,
            OrigemChart::class,
        ];
    }
}
