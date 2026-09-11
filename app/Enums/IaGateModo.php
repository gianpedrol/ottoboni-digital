<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IaGateModo: string implements HasLabel
{
    case Treinamento = 'treinamento';
    case AutoPorAcuracia = 'auto_por_acuracia';
    case AutoTotal = 'auto_total';

    public function getLabel(): string
    {
        return match ($this) {
            self::Treinamento => 'Treinamento — tudo passa por aprovação',
            self::AutoPorAcuracia => 'Automático por acurácia — libera o que já provou',
            self::AutoTotal => 'Automático total — sem aprovação (risco)',
        };
    }
}
