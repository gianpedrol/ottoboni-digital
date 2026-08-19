<?php

namespace App\Filament\Resources\Doctors;

use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Doctors\Schemas\DoctorForm;
use App\Filament\Resources\Doctors\Tables\DoctorsTable;
use App\Models\Doctor;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DoctorResource extends Resource
{
    protected static ?string $model = Doctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Administração';

    protected static ?string $modelLabel = 'médico';

    protected static ?string $pluralModelLabel = 'médicos';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return DoctorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        // Sem "create": os dois médicos vêm do seed; aqui só se ajusta
        // bot do Kommo, conta do Instagram e ativo/inativo.
        return [
            'index' => ListDoctors::route('/'),
            'edit' => EditDoctor::route('/{record}/edit'),
        ];
    }
}
