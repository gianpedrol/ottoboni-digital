<?php

namespace App\Filament\Resources\FollowupPlans;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\FollowupPlans\Pages\CreateFollowupPlan;
use App\Filament\Resources\FollowupPlans\Pages\EditFollowupPlan;
use App\Filament\Resources\FollowupPlans\Pages\ListFollowupPlans;
use App\Filament\Resources\FollowupPlans\Schemas\FollowupPlanForm;
use App\Filament\Resources\FollowupPlans\Tables\FollowupPlansTable;
use App\Models\FollowupPlan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FollowupPlanResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = FollowupPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'régua';

    protected static ?string $pluralModelLabel = 'réguas';

    protected static ?string $navigationLabel = 'Réguas';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    public static function form(Schema $schema): Schema
    {
        return FollowupPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FollowupPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowupPlans::route('/'),
            'create' => CreateFollowupPlan::route('/create'),
            'edit' => EditFollowupPlan::route('/{record}/edit'),
        ];
    }
}
