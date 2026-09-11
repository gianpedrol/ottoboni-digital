<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Telefone;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Agendamentos de hoje, já no escopo do usuário. Não entra sozinho no
 * dashboard: quem usa escolhe onde colocar.
 */
class AgendaHojeWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Agenda de hoje')
            ->description(config('painel.prototipo') ? 'Demonstração · dados fictícios' : null)
            ->query(fn (): Builder => $this->consulta())
            ->columns([
                TextColumn::make('inicio')
                    ->label('Horário')
                    ->formatStateUsing(fn (Appointment $record): string => $record->faixaHorario())
                    ->weight('medium'),
                TextColumn::make('patient.nome')
                    ->label('Paciente')
                    ->description(fn (Appointment $record): ?string => Telefone::paraMascara($record->patient?->telefone)),
                TextColumn::make('doctor.nome')
                    ->label('Médico'),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                Action::make('abrirAgenda')
                    ->label('Abrir agenda do dia')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->link()
                    ->url(fn (): string => Agenda::getUrl(['visao' => 'dia'])),
            ])
            ->defaultSort('inicio')
            // Cabe na tela sem empurrar o resto do dashboard; o dia inteiro está na Agenda
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nenhum agendamento hoje');
    }

    /**
     * @return Builder<Appointment>
     */
    private function consulta(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        $hoje = CarbonImmutable::now(Appointment::fuso())->startOfDay();

        return Appointment::query()
            ->visivelPara($user)
            ->entre($hoje, $hoje->addDay())
            ->with(['patient', 'doctor']);
    }
}
