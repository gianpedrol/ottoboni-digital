<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IaCanal: string implements HasLabel
{
    case Comentario = 'comentario';
    case Direct = 'direct';

    public function getLabel(): string
    {
        return match ($this) {
            self::Comentario => 'Comentário no post',
            self::Direct => 'Direct',
        };
    }
}
