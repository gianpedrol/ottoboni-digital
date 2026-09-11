<?php

namespace App\Filament\Resources\AutomationRuns\Schemas;

use App\Models\AutomationRun;
use App\Services\Automacoes\NomesDoKommo;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AutomationRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $nomes = new NomesDoKommo;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Execução')
                    ->schema([
                        TextEntry::make('regra')
                            ->label('Regra')
                            ->state(fn (AutomationRun $record): string => $record->rule?->rotulo() ?? '—'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Quando')
                            ->dateTime('d/m/Y H:i', timezone: (string) config('painel.timezone')),
                        TextEntry::make('lead_nome')
                            ->label('Lead')
                            ->placeholder('—'),
                        TextEntry::make('kommo_lead_id')
                            ->label('ID do lead no Kommo'),
                        TextEntry::make('pipeline_id')
                            ->label('Pipeline')
                            ->formatStateUsing(fn ($state): string => $nomes->pipeline((int) $state))
                            ->placeholder('—'),
                        TextEntry::make('motivo')
                            ->label('Motivo')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Evento')
                    ->description('O que aconteceu no Kommo e disparou a regra.')
                    ->schema([
                        ViewEntry::make('evento')
                            ->hiddenLabel()
                            ->view('filament.automacoes.json-entry'),
                    ]),
                Section::make('Ações')
                    ->description('O que a regra fez (ou faria), com a chamada exata para a API do Kommo.')
                    ->schema([
                        ViewEntry::make('acoes')
                            ->hiddenLabel()
                            ->view('filament.automacoes.acoes-entry'),
                    ]),
            ]);
    }
}
