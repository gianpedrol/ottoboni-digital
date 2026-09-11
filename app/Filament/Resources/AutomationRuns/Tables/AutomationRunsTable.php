<?php

namespace App\Filament\Resources\AutomationRuns\Tables;

use App\Enums\StatusExecucaoAutomacao;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Services\Automacoes\NomesDoKommo;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AutomationRunsTable
{
    public static function configure(Table $table): Table
    {
        $nomes = new NomesDoKommo;
        $tz = (string) config('painel.timezone');

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i', timezone: $tz)
                    ->sortable(),
                TextColumn::make('rule.codigo')
                    ->label('Regra')
                    ->badge()
                    ->color('gray')
                    ->description(fn (AutomationRun $record): ?string => $record->rule?->nome),
                TextColumn::make('lead_nome')
                    ->label('Lead')
                    ->description(fn (AutomationRun $record): string => "#{$record->kommo_lead_id}")
                    ->searchable(['lead_nome', 'kommo_lead_id']),
                TextColumn::make('pipeline_id')
                    ->label('Pipeline')
                    ->formatStateUsing(fn ($state): string => $nomes->pipeline((int) $state))
                    ->placeholder('—'),
                TextColumn::make('evento_descricao')
                    ->label('Evento')
                    ->state(fn (AutomationRun $record): string => (string) ($record->evento['descricao'] ?? '—'))
                    ->wrap(),
                TextColumn::make('acoes_resumo')
                    ->label('Ações')
                    ->state(function (AutomationRun $record): string {
                        $n = count(array_filter((array) $record->acoes, fn ($a): bool => (bool) ($a['aplicavel'] ?? true)));

                        return $n === 0 ? '—' : ($n === 1 ? '1 ação' : "{$n} ações");
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(50)
                    ->tooltip(fn (AutomationRun $record): ?string => $record->motivo)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('automation_rule_id')
                    ->label('Regra')
                    ->relationship('rule', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (AutomationRule $record): string => $record->rotulo()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusExecucaoAutomacao::class),
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('de')->label('De'),
                        DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['de'] ?? null, fn (Builder $q, $de) => $q->where(
                            'created_at', '>=', CarbonImmutable::parse($de, $tz)->startOfDay()->utc(),
                        ))
                        ->when($data['ate'] ?? null, fn (Builder $q, $ate) => $q->where(
                            'created_at', '<=', CarbonImmutable::parse($ate, $tz)->endOfDay()->utc(),
                        )))
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];

                        if ($data['de'] ?? null) {
                            $indicadores[] = 'De ' . CarbonImmutable::parse($data['de'])->format('d/m/Y');
                        }

                        if ($data['ate'] ?? null) {
                            $indicadores[] = 'Até ' . CarbonImmutable::parse($data['ate'])->format('d/m/Y');
                        }

                        return $indicadores;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhuma execução ainda')
            ->emptyStateDescription('Use o simulador e registre uma execução simulada para ver como fica o log.');
    }
}
