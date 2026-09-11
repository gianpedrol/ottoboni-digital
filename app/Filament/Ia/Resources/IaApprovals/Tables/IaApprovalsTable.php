<?php

namespace App\Filament\Ia\Resources\IaApprovals\Tables;

use App\Enums\IaApprovalStatus;
use App\Enums\IaCanal;
use App\Enums\IaGrauEdicao;
use App\Enums\IaIntent;
use App\Models\IaApproval;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IaApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m H:i')
                    ->sortable(),
                TextColumn::make('doctor.agente')
                    ->label('Agente')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                    ->toggleable(),
                TextColumn::make('canal')
                    ->label('Canal')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('intent')
                    ->label('Assunto')
                    ->badge(),
                // Coluna virtual: o texto vem do comentário ou do direct, então o
                // estado é calculado. Nomear a coluna com um método do model
                // faria o Filament procurar uma relação chamada assim.
                TextColumn::make('texto_da_pessoa')
                    ->label('O que perguntaram')
                    ->state(fn (IaApproval $r): string => $r->textoDaPessoa())
                    ->limit(60)
                    ->tooltip(fn (IaApproval $r): string => $r->textoDaPessoa())
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('grau_edicao')
                    ->label('Edição')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('score')
                    ->label('Nota')
                    ->numeric(2)
                    ->placeholder('—')
                    ->tooltip('Sem editar 1,00 · leve 0,80 · grande 0,40 · refeita/rejeitada 0'),
                TextColumn::make('revisor.name')
                    ->label('Revisado por')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('modelo')
                    ->label('Modelo')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(IaApprovalStatus::class)
                    ->multiple(),
                SelectFilter::make('intent')
                    ->label('Assunto')
                    ->options(IaIntent::class)
                    ->multiple(),
                SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(IaCanal::class),
                SelectFilter::make('grau_edicao')
                    ->label('Grau de edição')
                    ->options(IaGrauEdicao::class)
                    ->multiple(),
                Filter::make('so_erros')
                    ->label('Só o que deu errado')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                        IaApprovalStatus::Rejeitado,
                        IaApprovalStatus::Erro,
                        IaApprovalStatus::Expirado,
                    ])),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Revisão')
                    ->modalWidth('3xl'),
            ]);
    }
}
