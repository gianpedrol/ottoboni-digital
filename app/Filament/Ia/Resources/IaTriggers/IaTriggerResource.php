<?php

namespace App\Filament\Ia\Resources\IaTriggers;

use App\Filament\Ia\Resources\IaTriggers\Pages\ListIaTriggers;
use App\Filament\Ia\Resources\IaTriggers\Schemas\IaTriggerForm;
use App\Filament\Ia\Resources\IaTriggers\Tables\IaTriggersTable;
use App\Models\IaTrigger;
use App\Models\User;
use App\Support\AgenteSelecionado;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class IaTriggerResource extends Resource
{
    protected static ?string $model = IaTrigger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Configuração';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'gatilho';

    protected static ?string $pluralModelLabel = 'gatilhos';

    protected static ?string $navigationLabel = 'Gatilhos';

    public static function form(Schema $schema): Schema
    {
        return IaTriggerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IaTriggersTable::configure($table);
    }

    /** @return Builder<IaTrigger> */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        return parent::getEloquentQuery()
            ->whereIn('doctor_id', AgenteSelecionado::permitidos($user));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIaTriggers::route('/'),
        ];
    }
}
