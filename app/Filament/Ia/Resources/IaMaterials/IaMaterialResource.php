<?php

namespace App\Filament\Ia\Resources\IaMaterials;

use App\Filament\Ia\Resources\IaMaterials\Pages\ListIaMaterials;
use App\Filament\Ia\Resources\IaMaterials\Schemas\IaMaterialForm;
use App\Filament\Ia\Resources\IaMaterials\Tables\IaMaterialsTable;
use App\Models\IaMaterial;
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

/**
 * Imagens, PDFs e links que a agente pode mandar junto com a resposta.
 * A Dra. sobe o material do programa; o card do programa aponta para ele;
 * quando a pergunta é sobre aquele programa, vai aquele material.
 */
class IaMaterialResource extends Resource
{
    protected static ?string $model = IaMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Treinamento';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'material';

    protected static ?string $pluralModelLabel = 'materiais';

    protected static ?string $navigationLabel = 'Materiais (fotos e PDFs)';

    public static function form(Schema $schema): Schema
    {
        return IaMaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IaMaterialsTable::configure($table);
    }

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
            'index' => ListIaMaterials::route('/'),
        ];
    }
}
