<?php

namespace App\Filament\Ia\Resources\IaMaterials\Tables;

use App\Models\IaMaterial;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IaMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                ImageColumn::make('previa')
                    ->label('')
                    ->state(fn (IaMaterial $m): ?string => $m->tipo === 'imagem' ? $m->urlPublica() : null)
                    ->square()
                    ->size(56),
                TextColumn::make('nome')
                    ->label('Material')
                    ->searchable()
                    ->wrap()
                    ->description(fn (IaMaterial $m): string => '['.$m->codigo.']'.(filled($m->quando_usar) ? ' — '.$m->quando_usar : '')),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'imagem' => 'Imagem',
                        'documento' => 'PDF',
                        'video' => 'Vídeo',
                        default => 'Link',
                    }),
                TextColumn::make('doctor.agente')
                    ->label('Agente')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options(IaMaterial::TIPOS),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (IaMaterial $m): ?string => $m->urlPublica(), shouldOpenInNewTab: true)
                    ->visible(fn (IaMaterial $m): bool => $m->urlPublica() !== null),
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateHeading('Nenhum material ainda')
            ->emptyStateDescription('Suba a foto ou o PDF de cada programa e diga quando a agente deve mandar. Depois, no card do assunto, marque qual material vai junto.');
    }
}
