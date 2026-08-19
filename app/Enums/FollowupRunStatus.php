<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FollowupRunStatus: string implements HasColor, HasLabel
{
    case Agendado = 'agendado';
    case Enviado = 'enviado';
    case Falhou = 'falhou';
    case Cancelado = 'cancelado';
    case Respondido = 'respondido';

    public function getLabel(): string
    {
        return match ($this) {
            self::Agendado => 'Agendado',
            self::Enviado => 'Enviado',
            self::Falhou => 'Falhou',
            self::Cancelado => 'Cancelado',
            self::Respondido => 'Respondido',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Agendado => 'info',
            self::Enviado => 'success',
            self::Falhou => 'danger',
            self::Cancelado => 'gray',
            self::Respondido => 'success',
        };
    }
}
