<?php

namespace App\Filament\Resources\Services;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Services\Schemas\ServiceForm;
use App\Filament\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use App\Support\Financeiro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ServiceResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = Service::class;

    protected static ?string $slug = 'tabela-de-precos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'serviço';

    protected static ?string $pluralModelLabel = 'Tabela de preços';

    protected static ?string $navigationLabel = 'Tabela de preços';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function canAccess(): bool
    {
        return Financeiro::podeAcessar();
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
