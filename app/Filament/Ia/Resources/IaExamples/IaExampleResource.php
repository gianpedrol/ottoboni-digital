<?php

namespace App\Filament\Ia\Resources\IaExamples;

use App\Filament\Ia\Resources\IaExamples\Pages\ListIaExamples;
use App\Filament\Ia\Resources\IaExamples\Schemas\IaExampleForm;
use App\Filament\Ia\Resources\IaExamples\Tables\IaExamplesTable;
use App\Models\IaExample;
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

class IaExampleResource extends Resource
{
    protected static ?string $model = IaExample::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Treinamento';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'exemplo';

    protected static ?string $pluralModelLabel = 'exemplos';

    protected static ?string $navigationLabel = 'Exemplos aprovados';

    public static function form(Schema $schema): Schema
    {
        return IaExampleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IaExamplesTable::configure($table);
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
            'index' => ListIaExamples::route('/'),
        ];
    }
}
