<?php

namespace App\Filament\Resources\Doctors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome'),
                TextColumn::make('agente')
                    ->label('Agente')
                    ->badge(),
                TextColumn::make('kommo_pipeline_id')
                    ->label('Pipeline'),
                TextColumn::make('kommo_bot_id')
                    ->label('Salesbot')
                    ->placeholder('não configurado'),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->paginated(false);
    }
}
