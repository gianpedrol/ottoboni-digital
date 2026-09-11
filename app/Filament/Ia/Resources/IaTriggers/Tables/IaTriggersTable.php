<?php

namespace App\Filament\Ia\Resources\IaTriggers\Tables;

use App\Filament\Ia\Resources\IaTriggers\Schemas\IaTriggerForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
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
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => IaTriggerForm::TIPOS[$state] ?? $state),
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
            ])
            ->emptyStateIcon(Heroicon::OutlinedBolt)
            ->emptyStateHeading('Nenhum gatilho cadastrado')
            ->emptyStateDescription('Gatilhos são os termos que fazem a agente considerar responder um comentário — por exemplo "valor", "consulta" ou "agenda".');
    }
}
