<?php

namespace App\Filament\Ia\Resources\IaCards\Schemas;

use App\Models\User;
use App\Support\AgenteSelecionado;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class IaCardForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema->components([
            Section::make('Card da base')
                ->description('A agente só responde o que está aqui. Sem card, a pergunta cai como "não sei" e vai para a fila — é assim que a base cresce sem ela inventar.')
                ->schema([
                    Select::make('doctor_id')
                        ->label('Agente')
                        ->options(AgenteSelecionado::options($user))
                        ->required(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'validado' => 'Validado (a agente usa)',
                            'pendente' => 'Pendente (não usa ainda)',
                        ])
                        ->default('validado')
                        ->required(),
                    Textarea::make('pergunta')
                        ->label('Pergunta / situação')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('resposta')
                        ->label('Resposta')
                        ->rows(6)
                        ->required()
                        ->columnSpanFull()
                        ->helperText('Valores sempre no formato R$ 000,00, exatamente como a clínica cobra.'),
                    TagsInput::make('tags')
                        ->label('Tags')
                        ->columnSpanFull(),
                    Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
