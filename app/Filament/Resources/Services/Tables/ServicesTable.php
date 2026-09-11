<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\TipoServico;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('doctor'))
            ->defaultSort('valor', 'desc')
            ->columns([
                TextColumn::make('nome')
                    ->label('Serviço')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('doctor.nome')
                    ->label('Médico')
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TipoServico::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state): string => TipoServico::tryFrom($state)?->getColor() ?? 'gray'),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('duracao_min')
                    ->label('Duração')
                    ->suffix(' min')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('repasse_pct')
                    ->label('% repasse')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.') . '%')
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('doctor_id')
                    ->label('Médico')
                    ->relationship('doctor', 'nome'),
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoServico::class),
                TernaryFilter::make('ativo')
                    ->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhum serviço cadastrado')
            ->emptyStateDescription('Cadastre consultas, retornos, procedimentos e cirurgias com valor e % de repasse.');
    }
}
