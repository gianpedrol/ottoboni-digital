<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrigemRecebivel: string implements HasLabel
{
    case Contrato = 'contrato';
    case Consulta = 'consulta';
    case Procedimento = 'procedimento';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contrato => 'Contrato de cirurgia',
            self::Consulta => 'Consulta',
            self::Procedimento => 'Procedimento',
            self::Manual => 'Lançamento manual',
        };
    }
}
