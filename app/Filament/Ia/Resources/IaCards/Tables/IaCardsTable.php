<?php

namespace App\Filament\Ia\Resources\IaCards\Tables;

use App\Models\IaCard;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class IaCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // Pergunta e resposta na mesma célula: é o par que a agente
                // consulta, e duas colunas cortadas não deixavam ler nenhuma.
                TextColumn::make('pergunta')
                    ->label('Pergunta e resposta')
                    ->weight(FontWeight::Medium)
                    ->limit(90)
                    ->description(fn (IaCard $r): string => Str::limit($r->resposta, 160))
                    ->tooltip(fn (IaCard $r): string => $r->resposta)
                    ->searchable(['pergunta', 'resposta'])
                    ->wrap(),
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
                    ->formatStateUsing(fn (string $state): string => $state === 'validado' ? 'Validado' : 'Pendente')
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
            ])
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
            ->emptyStateHeading('A base ainda está vazia')
            ->emptyStateDescription('Sem card, a pergunta cai como "não sei" e vai para a fila. O que a equipe responde por lá vira card sozinho — cadastre aqui só as dúvidas que você já sabe que vão chegar.');
    }
}
