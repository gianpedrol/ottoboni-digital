<?php

namespace App\Filament\Resources\Receivables\Tables;

use App\Enums\FormaPagamento;
use App\Enums\OrigemRecebivel;
use App\Enums\StatusFinanceiro;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceivablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'doctor', 'installments']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('patient.nome')
                    ->label('Paciente')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('doctor.nome')
                    ->label('Médico')
                    ->toggleable(),
                TextColumn::make('valor_total')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('parcelas')
                    ->label('Parcelas pagas')
                    ->state(fn (Receivable $record): string => $record->installments->where('status', StatusFinanceiro::Pago->value)->count()
                        . '/' . $record->installments->count())
                    ->alignCenter(),
                TextColumn::make('proximo_vencimento')
                    ->label('Próx. vencimento')
                    ->state(fn (Receivable $record): ?string => $record->installments
                        ->first(fn (ReceivableInstallment $p): bool => $p->situacao() !== StatusFinanceiro::Pago
                            && $p->situacao() !== StatusFinanceiro::Cancelado)
                        ?->vencimento->format('d/m/Y'))
                    ->placeholder('—'),
                TextColumn::make('forma_pagamento')
                    ->label('Forma')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => FormaPagamento::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->formatStateUsing(fn (string $state): string => OrigemRecebivel::tryFrom($state)?->getLabel() ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('situacao')
                    ->label('Status')
                    ->state(fn (Receivable $record): StatusFinanceiro => $record->situacao())
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Lançado em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('situacao')
                    ->label('Status')
                    ->options(StatusFinanceiro::opcoesDeFiltro())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->scopes(['comSituacao' => [$data['value']]])
                        : $query),
                SelectFilter::make('doctor_id')
                    ->label('Médico')
                    ->relationship('doctor', 'nome'),
                SelectFilter::make('forma_pagamento')
                    ->label('Forma de pagamento')
                    ->options(FormaPagamento::class),
                SelectFilter::make('origem')
                    ->label('Origem')
                    ->options(OrigemRecebivel::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('Nenhuma conta a receber')
            ->emptyStateDescription('Lance uma consulta, procedimento ou contrato de cirurgia e as parcelas são geradas automaticamente.');
    }
}
