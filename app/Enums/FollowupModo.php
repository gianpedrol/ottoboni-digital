<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowupModo: string implements HasLabel
{
    case TextoFixo = 'texto_fixo';
    case Ia = 'ia';

    public function getLabel(): string
    {
        return match ($this) {
            self::TextoFixo => 'Texto fixo',
            self::Ia => 'Gerado por IA (ancorado no lead)',
        };
    }
}
