<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Enums\KommoSyncStatus;
use App\Enums\OrigemPaciente;
use App\Filament\Resources\Patients\FichaDoPaciente;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        $tz = FichaDoPaciente::tz();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('doctor')
                ->withMin([
                    'appointments as proxima_consulta' => fn (Builder $q) => $q
                        ->where('inicio', '>=', now())
                        ->whereNotIn('status', Patient::statusAgendaInativos()),
                ], 'inicio'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nome')
                    ->label('Paciente')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Patient $record): ?string => $record->procedimento_interesse),
                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable(),
                TextColumn::make('doctor.nome')
                    ->label('Médico')
                    ->placeholder('—'),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('kommo_sync_status')
                    ->label('Kommo')
                    ->badge(),
                TextColumn::make('proxima_consulta')
                    ->label('Próxima consulta')
                    ->dateTime('d/m/Y H:i', $tz)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->date('d/m/Y', $tz)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('doctor_id')
                    ->label('Médico')
                    ->relationship('doctor', 'nome', function (Builder $query): Builder {
                        /** @var User|null $user */
                        $user = Auth::user();

                        return $query->when($user, fn (Builder $q) => $q->whereIn('kommo_pipeline_id', $user->allowedPipelineIds()));
                    }),
                SelectFilter::make('origem')
                    ->label('Origem')
                    ->options(OrigemPaciente::class),
                SelectFilter::make('kommo_sync_status')
                    ->label('Status no Kommo')
                    ->options(KommoSyncStatus::class),
            ])
            ->recordUrl(fn (Patient $record): string => PatientResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('Nenhum paciente cadastrado')
            ->emptyStateDescription('Cadastre o primeiro paciente: o painel monta o contato (e, se quiser, o atendimento) para o Kommo.');
    }
}
