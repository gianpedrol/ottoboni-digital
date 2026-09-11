<?php

namespace App\Filament\Resources\Payables;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\Payables\Pages\CreatePayable;
use App\Filament\Resources\Payables\Pages\EditPayable;
use App\Filament\Resources\Payables\Pages\ListPayables;
use App\Filament\Resources\Payables\Schemas\PayableForm;
use App\Filament\Resources\Payables\Tables\PayablesTable;
use App\Filament\Resources\Payables\Widgets\PayablesStats;
use App\Models\Payable;
use App\Support\Financeiro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PayableResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = Payable::class;

    protected static ?string $slug = 'contas-a-pagar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'conta a pagar';

    protected static ?string $pluralModelLabel = 'contas a pagar';

    protected static ?string $navigationLabel = 'Contas a pagar';

    protected static ?string $recordTitleAttribute = 'fornecedor';

    public static function canAccess(): bool
    {
        return Financeiro::podeAcessar();
    }

    public static function form(Schema $schema): Schema
    {
        return PayableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayablesTable::configure($table);
    }

    public static function getWidgets(): array
    {
        return [
            PayablesStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayables::route('/'),
            'create' => CreatePayable::route('/create'),
            'edit' => EditPayable::route('/{record}/edit'),
        ];
    }
}
