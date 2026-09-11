<?php

namespace App\Filament\Resources\Payables\Tables;

use App\Enums\CategoriaDespesa;
use App\Enums\StatusFinanceiro;
use App\Models\Payable;
use App\Support\Financeiro;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('vencimento', 'desc')
            ->columns([
                TextColumn::make('fornecedor')
                    ->label('Fornecedor')
                    ->weight('medium')
                    ->description(fn (Payable $record): ?string => $record->descricao)
                    ->searchable(['fornecedor', 'descricao']),
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => CategoriaDespesa::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('pago_em')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('recorrente')
                    ->label('Recorrente')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedArrowPath)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->falseColor('gray'),
                TextColumn::make('situacao')
                    ->label('Status')
                    ->state(fn (Payable $record): StatusFinanceiro => $record->situacao())
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(CategoriaDespesa::class),
                SelectFilter::make('situacao')
                    ->label('Status')
                    ->options(StatusFinanceiro::opcoesDeFiltro())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->scopes(['comSituacao' => [$data['value']]])
                        : $query),
                Filter::make('vencimento')
                    ->label('Vencimento')
                    ->schema([
                        DatePicker::make('de')->label('Vencimento de')->displayFormat('d/m/Y'),
                        DatePicker::make('ate')->label('Vencimento até')->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['de'] ?? null, fn (Builder $q, string $de) => $q->whereDate('vencimento', '>=', $de))
                        ->when($data['ate'] ?? null, fn (Builder $q, string $ate) => $q->whereDate('vencimento', '<=', $ate)))
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];

                        if ($data['de'] ?? null) {
                            $indicadores[] = Indicator::make('Vence a partir de ' . CarbonImmutable::parse($data['de'])->format('d/m/Y'))
                                ->removeField('de');
                        }

                        if ($data['ate'] ?? null) {
                            $indicadores[] = Indicator::make('Vence até ' . CarbonImmutable::parse($data['ate'])->format('d/m/Y'))
                                ->removeField('ate');
                        }

                        return $indicadores;
                    }),
            ])
            ->recordActions([
                Action::make('marcarComoPaga')
                    ->label('Marcar como paga')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Payable $record): bool => $record->status !== StatusFinanceiro::Pago->value
                        && $record->status !== StatusFinanceiro::Cancelado->value)
                    ->modalHeading(fn (Payable $record): string => "Marcar como paga: {$record->fornecedor}")
                    ->modalDescription(fn (Payable $record): string => Financeiro::brl($record->valor)
                        . ' · vencimento ' . $record->vencimento->format('d/m/Y'))
                    ->modalSubmitActionLabel('Confirmar pagamento')
                    ->fillForm(fn (): array => ['pago_em' => Financeiro::hoje()->toDateString()])
                    ->schema([
                        DatePicker::make('pago_em')
                            ->label('Data do pagamento')
                            ->displayFormat('d/m/Y')
                            ->required(),
                    ])
                    ->action(function (Payable $record, array $data): void {
                        $record->marcarComoPaga($data['pago_em']);

                        Notification::make()
                            ->success()
                            ->title('Conta marcada como paga')
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma conta a pagar')
            ->emptyStateDescription('Cadastre aluguel, folha, fornecedores e demais despesas da clínica.');
    }
}
