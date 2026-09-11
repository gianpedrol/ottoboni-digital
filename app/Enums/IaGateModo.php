<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum IaGateModo: string implements HasColor, HasDescription, HasLabel
{
    case Treinamento = 'treinamento';
    case AutoPorAcuracia = 'auto_por_acuracia';
    case AutoTotal = 'auto_total';

    public function getLabel(): string
    {
        return match ($this) {
            self::Treinamento => 'Treinamento',
            self::AutoPorAcuracia => 'Automático por acurácia',
            self::AutoTotal => 'Automático total',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Treinamento => 'Tudo passa por aprovação. É por aqui que toda agente começa.',
            self::AutoPorAcuracia => 'Libera só os assuntos que já provaram acurácia na fila. O resto continua passando por humano.',
            self::AutoTotal => 'Responde sem aprovação, ignorando a acurácia. Use só com a fila vazia e alguém acompanhando.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Treinamento => 'info',
            self::AutoPorAcuracia => 'success',
            self::AutoTotal => 'danger',
        };
    }
}
