<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IaApprovalStatus: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Enviado = 'enviado';
    case Rejeitado = 'rejeitado';
    case Expirado = 'expirado';
    case AutoEnviado = 'auto_enviado';
    case Erro = 'erro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando revisão',
            self::Aprovado => 'Aprovado, enviando',
            self::Enviado => 'Enviado',
            self::Rejeitado => 'Rejeitado (silêncio)',
            self::Expirado => 'Expirado sem revisão',
            self::AutoEnviado => 'Enviado automático',
            self::Erro => 'Erro no envio',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Aprovado => 'info',
            self::Enviado, self::AutoEnviado => 'success',
            self::Rejeitado, self::Expirado => 'gray',
            self::Erro => 'danger',
        };
    }
}
