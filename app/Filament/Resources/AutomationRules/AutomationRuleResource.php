<?php

namespace App\Filament\Resources\AutomationRules;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\AutomationRules\Pages\CreateAutomationRule;
use App\Filament\Resources\AutomationRules\Pages\EditAutomationRule;
use App\Filament\Resources\AutomationRules\Pages\ListAutomationRules;
use App\Filament\Resources\AutomationRules\Schemas\AutomationRuleForm;
use App\Filament\Resources\AutomationRules\Tables\AutomationRulesTable;
use App\Models\AutomationRule;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AutomationRuleResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = AutomationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Automações';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'regra';

    protected static ?string $pluralModelLabel = 'regras de automação';

    protected static ?string $navigationLabel = 'Regras';

    protected static ?string $slug = 'automacoes/regras';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    public static function form(Schema $schema): Schema
    {
        return AutomationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationRules::route('/'),
            'create' => CreateAutomationRule::route('/create'),
            'edit' => EditAutomationRule::route('/{record}/edit'),
        ];
    }
}
