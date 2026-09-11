<?php

namespace App\Filament\Ia\Resources\IaCards\Schemas;

use App\Models\IaCard;
use App\Models\User;
use App\Support\AgenteSelecionado;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        ->options(IaCard::STATUS)
                        ->default('validado')
                        ->required(),
                    TextInput::make('categoria')
                        ->label('Assunto')
                        ->maxLength(150)
                        ->helperText('Como o card aparece para a agente. Ex.: "Investimento do Flor&Ser Raiz".'),
                    TextInput::make('modulo')
                        ->label('Módulo')
                        ->maxLength(80)
                        ->helperText('Agrupa os cards na base. Ex.: programas, agenda, local.'),
                    Textarea::make('pergunta')
                        ->label('Pergunta principal')
                        ->rows(2)
                        ->required()
                        ->columnSpanFull(),
                    TagsInput::make('perguntas_equivalentes')
                        ->label('Outras formas de perguntar')
                        ->placeholder('Digite e aperte Enter')
                        ->columnSpanFull()
                        ->helperText('Quanto mais formas de perguntar, mais fácil a agente reconhecer que este card responde.'),
                    Textarea::make('resposta')
                        ->label('Resposta oficial')
                        ->rows(5)
                        ->required()
                        ->columnSpanFull()
                        ->helperText('Valores sempre no formato R$ 000,00, exatamente como a clínica cobra.'),
                    Textarea::make('resposta_detalhada')
                        ->label('Detalhe e regra de uso')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Quando usar, quando não usar, o que nunca dizer junto. A agente lê isto como instrução.'),
                    TextInput::make('codigo')
                        ->label('Código')
                        ->maxLength(100)
                        ->helperText('Identificador estável (ex.: programas.raiz). Preenchido na importação; pode deixar em branco.'),
                    TagsInput::make('tags')
                        ->label('Tags'),
                    Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true)
                        ->helperText('Desligado, o card some do prompt sem ser apagado.'),
                ])
                ->columns(2),
        ]);
    }
}
