<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação do cadastro do paciente em relação ao Kommo.
 * "Simulado" = modo protótipo: o payload foi montado e guardado, nada saiu.
 */
enum KommoSyncStatus: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case Simulado = 'simulado';
    case Sincronizado = 'sincronizado';
    case Erro = 'erro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Simulado => 'Simulado',
            self::Sincronizado => 'Sincronizado',
            self::Erro => 'Erro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendente => 'gray',
            self::Simulado => 'warning',
            self::Sincronizado => 'success',
            self::Erro => 'danger',
        };
    }
}
