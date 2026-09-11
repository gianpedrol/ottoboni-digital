<?php

namespace App\Filament\Resources\FollowupRuns;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\FollowupRuns\Pages\ListFollowupRuns;
use App\Filament\Resources\FollowupRuns\Tables\FollowupRunsTable;
use App\Models\FollowupRun;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FollowupRunResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = FollowupRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'execução';

    protected static ?string $pluralModelLabel = 'execuções';

    protected static ?string $navigationLabel = 'Execuções';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        // O escopo do usuário vale aqui também: recepção só vê execuções
        // do médico dela.
        return parent::getEloquentQuery()
            ->whereHas('doctor', fn ($q) => $q->whereIn('kommo_pipeline_id', $user->allowedPipelineIds()))
            ->with(['plan', 'step', 'doctor']);
    }

    public static function table(Table $table): Table
    {
        return FollowupRunsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowupRuns::route('/'),
        ];
    }
}
