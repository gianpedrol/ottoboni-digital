<?php

namespace App\Filament\Resources\BankTransactions;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Resources\BankTransactions\Tables\BankTransactionsTable;
use App\Filament\Resources\BankTransactions\Widgets\BankAccountsStats;
use App\Models\BankTransaction;
use App\Support\Financeiro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BankTransactionResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = BankTransaction::class;

    protected static ?string $slug = 'extrato-bancario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'lançamento';

    protected static ?string $pluralModelLabel = 'Extrato bancário';

    protected static ?string $navigationLabel = 'Extrato bancário';

    public static function canAccess(): bool
    {
        return Financeiro::podeAcessar();
    }

    // Lançamento vem do banco (OFX): não se cria nem edita à mão.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return BankTransactionsTable::configure($table);
    }

    public static function getWidgets(): array
    {
        return [
            BankAccountsStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
        ];
    }
}
