<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrigemTexto: string implements HasLabel
{
    case IaAncorada = 'ia_ancorada';
    case Template = 'template';
    case Fixo = 'fixo';

    public function getLabel(): string
    {
        return match ($this) {
            self::IaAncorada => 'IA ancorada no lead',
            self::Template => 'Template (fallback)',
            self::Fixo => 'Texto fixo',
        };
    }
}
