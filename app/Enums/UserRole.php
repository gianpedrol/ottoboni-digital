<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Gestor = 'gestor';
    case Recepcao = 'recepcao';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Gestor => 'Gestor',
            self::Recepcao => 'Recepção',
        };
    }
}
