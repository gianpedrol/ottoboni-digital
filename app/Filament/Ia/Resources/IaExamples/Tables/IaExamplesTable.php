<?php

namespace App\Filament\Ia\Resources\IaExamples\Tables;

use App\Enums\IaIntent;
use App\Models\IaExample;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

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
                    ->label('Pergunta e resposta correta')
                    ->weight(FontWeight::Medium)
                    ->limit(80)
                    ->description(fn (IaExample $r): string => Str::limit($r->resposta, 140))
                    ->tooltip(fn (IaExample $r): string => $r->resposta)
                    ->searchable(['pergunta', 'resposta'])
                    ->wrap(),
                TextColumn::make('resposta_rejeitada')
                    ->label('Contra-exemplo')
                    ->limit(40)
                    ->color('danger')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->sortable(),
                TextColumn::make('approval_id')
                    ->label('Origem')
                    ->formatStateUsing(fn (?int $state): string => $state ? 'Revisão #'.$state : 'Manual')
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
            ])
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap)
            ->emptyStateHeading('Nenhum exemplo ainda')
            ->emptyStateDescription('Eles nascem sozinhos quando alguém corrige uma resposta na fila de aprovação. Cadastre à mão só o que você quer ensinar antes de acontecer.');
    }
}
