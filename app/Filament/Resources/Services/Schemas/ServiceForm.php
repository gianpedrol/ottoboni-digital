<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\TipoServico;
use App\Models\Doctor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Serviço')
                    ->schema([
                        Select::make('doctor_id')
                            ->label('Médico')
                            ->options(Doctor::query()->orderBy('nome')->pluck('nome', 'id'))
                            ->required(),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoServico::class)
                            ->required(),
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(120)
                            ->columnSpanFull(),
                        TextInput::make('valor')
                            ->label('Valor')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('R$')
                            ->required(),
                        TextInput::make('duracao_min')
                            ->label('Duração')
                            ->numeric()
                            ->integer()
                            ->minValue(5)
                            ->default(30)
                            ->suffix('min')
                            ->required(),
                        TextInput::make('repasse_pct')
                            ->label('Repasse ao médico')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->required()
                            ->helperText('Percentual do valor recebido que vai para o médico.'),
                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
