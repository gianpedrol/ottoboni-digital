<?php

namespace App\Filament\Ia\Resources\IaTriggers\Schemas;

use App\Models\User;
use App\Support\AgenteSelecionado;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class IaTriggerForm
{
    /** Também usados como rótulo na tabela. */
    public const TIPOS = [
        'palavra_chave' => 'Palavra-chave',
        'frase' => 'Frase exata',
        'regex' => 'Expressão regular',
    ];

    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema->components([
            Section::make('Gatilho de comentário')
                ->description('Termos que fazem a agente considerar responder um comentário. Substitui a lista fixa que estava no fluxo do n8n.')
                ->schema([
                    Select::make('doctor_id')
                        ->label('Agente')
                        ->options(AgenteSelecionado::options($user))
                        ->required(),
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(self::TIPOS)
                        ->default('palavra_chave')
                        ->required(),
                    TextInput::make('termo')
                        ->label('Termo')
                        ->required()
                        ->maxLength(190)
                        ->columnSpanFull(),
                    TextInput::make('ordem')
                        ->label('Ordem')
                        ->numeric()
                        ->default(0),
                    Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
