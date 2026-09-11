<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TipoAgendamento: string implements HasColor, HasIcon, HasLabel
{
    case Consulta = 'consulta';
    case Retorno = 'retorno';
    case Online = 'online';
    case Procedimento = 'procedimento';
    case Cirurgia = 'cirurgia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consulta => 'Consulta',
            self::Retorno => 'Retorno',
            self::Online => 'Online',
            self::Procedimento => 'Procedimento',
            self::Cirurgia => 'Cirurgia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            // Paleta calma: o ícone diferencia o tipo; cor forte só na cirurgia (coral da marca)
            self::Consulta => 'gray',
            self::Retorno => 'gray',
            self::Online => 'info',
            self::Procedimento => 'warning',
            self::Cirurgia => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Consulta => Heroicon::OutlinedUserCircle,
            self::Retorno => Heroicon::OutlinedArrowUturnLeft,
            self::Online => Heroicon::OutlinedVideoCamera,
            self::Procedimento => Heroicon::OutlinedSparkles,
            self::Cirurgia => Heroicon::OutlinedScissors,
        };
    }

    /**
     * Tipo sugerido a partir do tipo do serviço da tabela de preços.
     */
    public static function doServico(?string $tipoServico): ?self
    {
        return self::tryFrom((string) $tipoServico);
    }
}
