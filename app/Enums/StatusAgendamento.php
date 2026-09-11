<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum StatusAgendamento: string implements HasColor, HasIcon, HasLabel
{
    case Agendado = 'agendado';
    case Confirmado = 'confirmado';
    case Realizado = 'realizado';
    case Faltou = 'faltou';
    case Cancelado = 'cancelado';
    case Remarcado = 'remarcado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Agendado => 'Agendado',
            self::Confirmado => 'Confirmado',
            self::Realizado => 'Realizado',
            self::Faltou => 'Faltou',
            self::Cancelado => 'Cancelado',
            self::Remarcado => 'Remarcado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Agendado => 'info',
            self::Confirmado => 'success',
            self::Realizado => 'gray',
            self::Faltou => 'danger',
            self::Cancelado => 'gray',
            self::Remarcado => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Agendado => Heroicon::OutlinedClock,
            self::Confirmado => Heroicon::OutlinedCheckCircle,
            self::Realizado => Heroicon::OutlinedCheckBadge,
            self::Faltou => Heroicon::OutlinedXCircle,
            self::Cancelado => Heroicon::OutlinedNoSymbol,
            self::Remarcado => Heroicon::OutlinedArrowPath,
        };
    }

    /**
     * Ainda vai acontecer: dá para confirmar, remarcar, cancelar etc.
     */
    public function emAberto(): bool
    {
        return in_array($this, [self::Agendado, self::Confirmado], true);
    }

    /**
     * Status que liberam o horário (não contam no conflito).
     *
     * @return array<int, self>
     */
    public static function liberamHorario(): array
    {
        return [self::Cancelado, self::Remarcado];
    }
}
