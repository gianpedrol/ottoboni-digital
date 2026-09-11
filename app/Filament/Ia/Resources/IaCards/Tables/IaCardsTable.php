<?php

namespace App\Filament\Ia\Resources\IaCards\Tables;

use App\Models\IaCard;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IaCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('pergunta')
                    ->label('Pergunta')
                    ->limit(70)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('resposta')
                    ->label('Resposta')
                    ->limit(70)
                    ->searchable()
                    ->wrap()
                    ->tooltip(fn (IaCard $r): string => $r->resposta),
                TextColumn::make('doctor.agente')
                    ->label('Agente')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                    ->toggleable(),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'painel_aprovacao' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'painel_aprovacao' => 'Nasceu de uma revisão',
                        'base_inicial' => 'Base inicial',
                        default => 'Manual',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'validado' ? 'success' : 'warning'),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['validado' => 'Validado', 'pendente' => 'Pendente']),
                Filter::make('nasceu_de_revisao')
                    ->label('Só os que nasceram de uma revisão')
                    ->query(fn (Builder $query): Builder => $query->where('origem', 'painel_aprovacao')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
