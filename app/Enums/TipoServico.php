<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoServico: string implements HasColor, HasLabel
{
    case Consulta = 'consulta';
    case Retorno = 'retorno';
    case Procedimento = 'procedimento';
    case Cirurgia = 'cirurgia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consulta => 'Consulta',
            self::Retorno => 'Retorno',
            self::Procedimento => 'Procedimento',
            self::Cirurgia => 'Cirurgia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Consulta => 'info',
            self::Retorno => 'gray',
            self::Procedimento => 'warning',
            self::Cirurgia => 'primary',
        };
    }
}
