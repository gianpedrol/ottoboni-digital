<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DoctorScope: string implements HasLabel
{
    case Eduardo = 'eduardo';
    case Vanessa = 'vanessa';
    case Ambos = 'ambos';

    public function getLabel(): string
    {
        return match ($this) {
            self::Eduardo => 'Dr. Eduardo Ottoboni',
            self::Vanessa => 'Dra. Vanessa Ottoboni',
            self::Ambos => 'Os dois médicos',
        };
    }
}
