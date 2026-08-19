<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Ao editar, deixe em branco para manter a senha atual.'),
                Select::make('role')
                    ->label('Papel')
                    ->options(UserRole::class)
                    ->default(UserRole::Recepcao->value)
                    ->required()
                    ->helperText('Admin vê tudo; gestor vê os dois médicos mas não mexe em configuração; recepção vê só o médico do seu escopo.'),
                Select::make('doctor_scope')
                    ->label('Escopo de médico')
                    ->options(DoctorScope::class)
                    ->default(DoctorScope::Ambos->value)
                    ->required()
                    ->helperText('Para recepção, limita quais atendimentos aparecem. Para admin e gestor não restringe nada.'),
            ]);
    }
}
