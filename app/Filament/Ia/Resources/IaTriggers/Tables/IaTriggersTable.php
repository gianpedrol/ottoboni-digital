<?php

namespace App\Filament\Ia\Resources\IaTriggers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IaTriggersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->reorderable('ordem')
            ->columns([
                TextColumn::make('termo')
                    ->label('Termo')
                    ->searchable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('doctor.agente')
                    ->label('Agente')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
