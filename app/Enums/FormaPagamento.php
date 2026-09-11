<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormaPagamento: string implements HasLabel
{
    case Pix = 'pix';
    case Cartao = 'cartao';
    case Boleto = 'boleto';
    case Dinheiro = 'dinheiro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Cartao => 'Cartão',
            self::Boleto => 'Boleto',
            self::Dinheiro => 'Dinheiro',
        };
    }
}
