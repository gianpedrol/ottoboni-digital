<?php

namespace App\Filament\Ia\Resources\IaExamples\Tables;

use App\Enums\IaIntent;
use App\Models\IaExample;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IaExamplesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('prioridade', 'desc')
            ->columns([
                TextColumn::make('intent')
                    ->label('Assunto')
                    ->badge(),
                TextColumn::make('pergunta')
                    ->label('Pergunta')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('resposta')
                    ->label('Resposta correta')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (IaExample $r): string => $r->resposta),
                TextColumn::make('resposta_rejeitada')
                    ->label('Contra-exemplo')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->sortable(),
                TextColumn::make('approval_id')
                    ->label('Origem')
                    ->formatStateUsing(fn (?int $state): string => $state ? 'Revisão #' . $state : 'Manual')
                    ->toggleable(),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('intent')
                    ->label('Assunto')
                    ->options(IaIntent::class)
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
