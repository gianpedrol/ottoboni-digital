<?php

namespace App\Filament\Ia\Resources\IaExamples\Schemas;

use App\Enums\IaIntent;
use App\Models\User;
use App\Support\AgenteSelecionado;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class IaExampleForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema->components([
            Section::make('Exemplo de treino')
                ->description('Vai para o prompt da agente como exemplo. A maioria nasce sozinha quando alguém corrige uma resposta na fila.')
                ->schema([
                    Select::make('doctor_id')
                        ->label('Agente')
                        ->options(AgenteSelecionado::options($user))
                        ->required(),
                    Select::make('intent')
                        ->label('Assunto')
                        ->options(IaIntent::class)
                        ->required(),
                    Textarea::make('pergunta')
                        ->label('O que a paciente escreveu')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('resposta')
                        ->label('Resposta correta')
                        ->rows(5)
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('resposta_rejeitada')
                        ->label('Resposta a evitar (contra-exemplo)')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Opcional, mas é o que mais ensina: mostra à agente exatamente o erro que ela cometeu.'),
                    TextInput::make('prioridade')
                        ->label('Prioridade')
                        ->numeric()
                        ->default(0)
                        ->helperText('Maior aparece primeiro no prompt. Correções de "não sei" entram com 10.'),
                    Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
