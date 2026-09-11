<?php

namespace App\Filament\Resources\Receivables\Schemas;

use App\Enums\FormaPagamento;
use App\Enums\OrigemRecebivel;
use App\Enums\StatusFinanceiro;
use App\Models\Receivable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceivableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conta a receber')
                    ->schema([
                        TextEntry::make('patient.nome')
                            ->label('Paciente')
                            ->placeholder('Sem paciente vinculado'),
                        TextEntry::make('doctor.nome')
                            ->label('Médico')
                            ->placeholder('—'),
                        TextEntry::make('service.nome')
                            ->label('Serviço')
                            ->placeholder('—'),
                        TextEntry::make('descricao')
                            ->label('Descrição'),
                        TextEntry::make('forma_pagamento')
                            ->label('Forma de pagamento')
                            ->formatStateUsing(fn (string $state): string => FormaPagamento::tryFrom($state)?->getLabel() ?? $state),
                        TextEntry::make('origem')
                            ->label('Origem')
                            ->formatStateUsing(fn (string $state): string => OrigemRecebivel::tryFrom($state)?->getLabel() ?? $state),
                    ])
                    ->columns(3),

                Section::make('Resumo')
                    ->schema([
                        TextEntry::make('valor_total')
                            ->label('Valor total')
                            ->money('BRL'),
                        TextEntry::make('recebido')
                            ->label('Recebido')
                            ->state(fn (Receivable $record): float => $record->valorRecebido())
                            ->money('BRL')
                            ->color('success'),
                        TextEntry::make('em_aberto')
                            ->label('Em aberto')
                            ->state(fn (Receivable $record): float => $record->valorEmAberto())
                            ->money('BRL'),
                        TextEntry::make('situacao')
                            ->label('Situação')
                            ->state(fn (Receivable $record): StatusFinanceiro => $record->situacao())
                            ->badge(),
                    ])
                    ->columns(4),
            ]);
    }
}
