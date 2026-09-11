<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusExecucaoAutomacao: string implements HasColor, HasLabel
{
    case Registrado = 'registrado';
    case Executado = 'executado';
    case Ignorado = 'ignorado';
    case Falhou = 'falhou';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registrado => 'Só registrado',
            self::Executado => 'Executado',
            self::Ignorado => 'Ignorado',
            self::Falhou => 'Falhou',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registrado => 'info',
            self::Executado => 'success',
            self::Ignorado => 'gray',
            self::Falhou => 'danger',
        };
    }
}
