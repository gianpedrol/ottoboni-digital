<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Concerns\TituloEmPortugues;
use App\Enums\UserRole;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ImprimirRegistro;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Filament\Resources\Patients\Schemas\PatientInfolist;
use App\Filament\Resources\Patients\Tables\PatientsTable;
use App\Models\Patient;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PatientResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Clínica';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'paciente';

    protected static ?string $pluralModelLabel = 'pacientes';

    protected static ?string $recordTitleAttribute = 'nome';

    /**
     * Escopo aplicado na consulta (vale para lista, busca e URL direta):
     * recepção só vê pacientes dos médicos a que tem acesso.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = self::usuario();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return Patient::restringirPara($query, $user);
    }

    public static function form(Schema $schema): Schema
    {
        return PatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientsTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return self::usuario()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Prontuário: admin e gestor. No produto final, o médico responsável
     * (ainda não existe papel de médico). Mesma regra da MedicalRecordPolicy.
     */
    public static function podeVerProntuario(): bool
    {
        $user = self::usuario();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    /**
     * Financeiro do paciente: a recepção não vê (escopo da Fase 3).
     */
    public static function podeVerFinanceiro(): bool
    {
        $user = self::usuario();

        return $user !== null && $user->role !== UserRole::Recepcao;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'view' => ViewPatient::route('/{record}'),
            'edit' => EditPatient::route('/{record}/edit'),
            'imprimir' => ImprimirRegistro::route('/{record}/prontuario/{registroId}/imprimir'),
        ];
    }

    private static function usuario(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }
}
