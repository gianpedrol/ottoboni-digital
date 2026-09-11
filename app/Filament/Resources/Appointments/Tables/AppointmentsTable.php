<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Filament\Resources\Appointments\Actions\AcoesDoAgendamento;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use App\Support\Telefone;
use Carbon\CarbonImmutable;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        $fuso = Appointment::fuso();

        return $table
            ->columns([
                TextColumn::make('inicio')
                    ->label('Data')
                    ->dateTime('d/m/Y', timezone: $fuso)
                    ->description(fn (Appointment $record): string => $record->faixaHorario())
                    ->sortable(),
                TextColumn::make('patient.nome')
                    ->label('Paciente')
                    ->weight('medium')
                    ->description(fn (Appointment $record): ?string => Telefone::paraMascara($record->patient?->telefone))
                    ->searchable(),
                TextColumn::make('doctor.nome')
                    ->label('Médico'),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('service.nome')
                    ->label('Serviço')
                    ->limit(30)
                    ->placeholder('—'),
                TextColumn::make('sala')
                    ->label('Sala')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('kommo_lead_id')
                    ->label('Lead Kommo')
                    ->placeholder('sem lead')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('doctor_id')
                    ->label('Médico')
                    ->options(fn (): array => AppointmentForm::medicosPermitidos()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(StatusAgendamento::class),
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->multiple()
                    ->options(TipoAgendamento::class),
                Filter::make('periodo')
                    ->schema([
                        DatePicker::make('de')
                            ->label('De')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('ate')
                            ->label('Até')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data) use ($fuso): Builder {
                        if (filled($data['de'] ?? null)) {
                            $query->where('inicio', '>=', Appointment::paraBanco(CarbonImmutable::parse($data['de'], $fuso)->startOfDay()));
                        }

                        if (filled($data['ate'] ?? null)) {
                            $query->where('inicio', '<', Appointment::paraBanco(CarbonImmutable::parse($data['ate'], $fuso)->addDay()->startOfDay()));
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];

                        if (filled($data['de'] ?? null)) {
                            $indicadores[] = 'De ' . CarbonImmutable::parse($data['de'])->format('d/m/Y');
                        }

                        if (filled($data['ate'] ?? null)) {
                            $indicadores[] = 'Até ' . CarbonImmutable::parse($data['ate'])->format('d/m/Y');
                        }

                        return $indicadores;
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ActionGroup::make(AcoesDoAgendamento::todas())
                    ->label('Ações')
                    ->button()
                    ->color('gray'),
                EditAction::make(),
            ])
            ->defaultSort('inicio', 'desc')
            ->emptyStateHeading('Nenhum agendamento')
            ->emptyStateDescription('Crie pelo botão acima ou clicando num horário livre na Agenda.');
    }
}
