<?php

namespace App\Filament\Resources\Receivables;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\Receivables\Pages\CreateReceivable;
use App\Filament\Resources\Receivables\Pages\EditReceivable;
use App\Filament\Resources\Receivables\Pages\ListReceivables;
use App\Filament\Resources\Receivables\Pages\ViewReceivable;
use App\Filament\Resources\Receivables\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\Receivables\Schemas\ReceivableForm;
use App\Filament\Resources\Receivables\Schemas\ReceivableInfolist;
use App\Filament\Resources\Receivables\Tables\ReceivablesTable;
use App\Filament\Resources\Receivables\Widgets\ReceivablesStats;
use App\Models\Receivable;
use App\Support\Financeiro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReceivableResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = Receivable::class;

    protected static ?string $slug = 'contas-a-receber';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'conta a receber';

    protected static ?string $pluralModelLabel = 'contas a receber';

    protected static ?string $navigationLabel = 'Contas a receber';

    protected static ?string $recordTitleAttribute = 'descricao';

    public static function canAccess(): bool
    {
        return Financeiro::podeAcessar();
    }

    public static function form(Schema $schema): Schema
    {
        return ReceivableForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReceivableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceivablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ReceivablesStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceivables::route('/'),
            'create' => CreateReceivable::route('/create'),
            'view' => ViewReceivable::route('/{record}'),
            'edit' => EditReceivable::route('/{record}/edit'),
        ];
    }
}
