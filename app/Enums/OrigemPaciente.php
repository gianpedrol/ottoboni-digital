<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Origem do paciente. O valor gravado é o próprio rótulo, igual ao campo
 * "Origem" que as agentes já preenchem no Kommo (ex.: "Orgânico-IA").
 */
enum OrigemPaciente: string implements HasLabel
{
    case Instagram = 'Instagram';
    case Indicacao = 'Indicação';
    case Google = 'Google';
    case TrafegoPago = 'Tráfego pago';
    case OrganicoIa = 'Orgânico-IA';

    public function getLabel(): string
    {
        return $this->value;
    }
}
