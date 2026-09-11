<?php

namespace App\Filament\Resources\AutomationRuns;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\AutomationRuns\Pages\ListAutomationRuns;
use App\Filament\Resources\AutomationRuns\Pages\ViewAutomationRun;
use App\Filament\Resources\AutomationRuns\Schemas\AutomationRunInfolist;
use App\Filament\Resources\AutomationRuns\Tables\AutomationRunsTable;
use App\Models\AutomationRun;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Log das automações — somente leitura.
 */
class AutomationRunResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = AutomationRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Automações';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'execução';

    protected static ?string $pluralModelLabel = 'execuções de automação';

    protected static ?string $navigationLabel = 'Execuções';

    protected static ?string $slug = 'automacoes/execucoes';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('rule');
    }

    public static function table(Table $table): Table
    {
        return AutomationRunsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AutomationRunInfolist::configure($schema);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationRuns::route('/'),
            'view' => ViewAutomationRun::route('/{record}'),
        ];
    }
}
