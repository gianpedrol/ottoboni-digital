<?php

namespace App\Filament\Ia\Resources\IaMaterials\Schemas;

use App\Models\IaMaterial;
use App\Models\User;
use App\Support\AgenteSelecionado;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class IaMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema->components([
            Section::make('Material')
                ->description('O que a agente pode mandar junto com a resposta. Seja específico: um material por programa, por assunto. Depois, no card daquele assunto, marque qual vai junto.')
                ->schema([
                    Select::make('doctor_id')
                        ->label('Agente')
                        ->options(AgenteSelecionado::options($user))
                        ->default(AgenteSelecionado::resolver($user, null))
                        ->required(),
                    TextInput::make('nome')
                        ->label('Nome')
                        ->placeholder('Ex.: Foto do programa Flor&Ser Raiz')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if (blank($get('codigo')) && filled($state)) {
                                $set('codigo', IaMaterial::normalizarCodigo($state));
                            }
                        }),
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(IaMaterial::TIPOS)
                        ->default('imagem')
                        ->required()
                        ->live(),
                    FileUpload::make('arquivo')
                        ->label('Arquivo')
                        ->disk('public')
                        ->directory('ia-materiais')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(8192)
                        ->imagePreviewHeight('160')
                        ->helperText('Foto (JPG/PNG) ou PDF de até 8 MB. Pelo celular dá para tirar a foto na hora.')
                        ->visible(fn (Get $get): bool => in_array($get('tipo'), IaMaterial::COM_ARQUIVO, true))
                        ->required(fn (Get $get): bool => in_array($get('tipo'), IaMaterial::COM_ARQUIVO, true)),
                    TextInput::make('url')
                        ->label('Link')
                        ->url()
                        ->maxLength(1024)
                        ->placeholder('https://www.instagram.com/reel/...')
                        ->visible(fn (Get $get): bool => ! in_array($get('tipo'), IaMaterial::COM_ARQUIVO, true))
                        ->required(fn (Get $get): bool => ! in_array($get('tipo'), IaMaterial::COM_ARQUIVO, true)),
                    Textarea::make('quando_usar')
                        ->label('Quando a agente deve mandar')
                        ->rows(3)
                        ->placeholder('Ex.: quando a pessoa perguntar do Flor&Ser Raiz, do que inclui ou do investimento')
                        ->helperText('A agente lê isto como instrução. Quanto mais específico, menos ela manda a coisa errada.'),
                    TextInput::make('codigo')
                        ->label('Código')
                        ->maxLength(60)
                        ->regex('/^[a-z0-9_]+$/')
                        ->helperText('Nome curto que a agente usa por dentro. Preenchido sozinho a partir do nome.'),
                    Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(1),
        ]);
    }
}
