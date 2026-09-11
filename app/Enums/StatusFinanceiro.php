<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação de parcela a receber e de conta a pagar. No banco só se grava
 * aberto, pago ou cancelado; "atrasado" é calculado (aberto com vencimento
 * passado), para não depender de job virando status à meia-noite.
 */
enum StatusFinanceiro: string implements HasColor, HasLabel
{
    case Aberto = 'aberto';
    case Pago = 'pago';
    case Atrasado = 'atrasado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aberto => 'Em aberto',
            self::Pago => 'Pago',
            self::Atrasado => 'Atrasado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aberto => 'warning',
            self::Pago => 'success',
            self::Atrasado => 'danger',
            self::Cancelado => 'gray',
        };
    }

    /**
     * Opções dos filtros de status (cancelado fica de fora das telas).
     *
     * @return array<string, string>
     */
    public static function opcoesDeFiltro(): array
    {
        return [
            self::Aberto->value => self::Aberto->getLabel(),
            self::Atrasado->value => self::Atrasado->getLabel(),
            self::Pago->value => self::Pago->getLabel(),
        ];
    }
}
