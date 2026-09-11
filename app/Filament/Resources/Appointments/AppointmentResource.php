<?php

namespace App\Filament\Resources\Appointments;

use App\Filament\Concerns\TituloEmPortugues;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AppointmentResource extends Resource
{
    use TituloEmPortugues;

    protected static ?string $model = Appointment::class;

    protected static ?string $slug = 'agendamentos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Clínica';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'agendamento';

    protected static ?string $pluralModelLabel = 'agendamentos';

    protected static ?string $navigationLabel = 'Agendamentos';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        // Recepção só vê (e só abre por URL) agendamentos dos seus médicos.
        return Appointment::restringirAoUsuario(parent::getEloquentQuery(), $user)
            ->with(['patient', 'doctor', 'service']);
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof Appointment) {
            return parent::getRecordTitle($record);
        }

        return ($record->patient->nome ?? 'Agendamento') . ' — ' . $record->inicioLocal()->format('d/m/Y H:i');
    }

    public static function form(Schema $schema): Schema
    {
        return AppointmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
    }

    // Agendamento não se apaga: cancela ou remarca, para manter o histórico.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'edit' => EditAppointment::route('/{record}/edit'),
        ];
    }
}
