<?php

namespace App\Filament\Ia\Resources\IaCards;

use App\Filament\Ia\Resources\IaCards\Pages\ListIaCards;
use App\Filament\Ia\Resources\IaCards\Schemas\IaCardForm;
use App\Filament\Ia\Resources\IaCards\Tables\IaCardsTable;
use App\Models\IaCard;
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

class IaCardResource extends Resource
{
    protected static ?string $model = IaCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Treinamento';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'card';

    protected static ?string $pluralModelLabel = 'cards';

    protected static ?string $navigationLabel = 'Base de conhecimento';

    public static function form(Schema $schema): Schema
    {
        return IaCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IaCardsTable::configure($table);
    }

    /** @return Builder<IaCard> */
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
            'index' => ListIaCards::route('/'),
        ];
    }
}
