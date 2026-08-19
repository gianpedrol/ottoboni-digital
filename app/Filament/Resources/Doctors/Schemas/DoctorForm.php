<?php

namespace App\Filament\Resources\Doctors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome')
                    ->required(),
                TextInput::make('agente')
                    ->label('Agente de IA')
                    ->disabled()
                    ->helperText('Definido pelos workflows do n8n — não muda por aqui.'),
                TextInput::make('kommo_pipeline_id')
                    ->label('Pipeline no Kommo')
                    ->numeric()
                    ->disabled()
                    ->helperText('Vem do briefing/n8n. Mudar isso troca o funil inteiro do médico.'),
                TextInput::make('ig_user_id')
                    ->label('ID da conta do Instagram')
                    ->helperText('Usado pelo n8n para enviar DM (Fase 3).'),
                TextInput::make('kommo_bot_id')
                    ->label('ID do Salesbot no Kommo')
                    ->numeric()
                    ->helperText('Bot disparado como follow-up quando a janela de 24h do Instagram fechou (Fase 2).'),
                Toggle::make('ativo')
                    ->label('Ativo'),
            ]);
    }
}
