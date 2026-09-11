<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * O quanto o humano precisou mexer no rascunho. É daqui que sai a acurácia:
 * não é a IA se avaliando, é o trabalho que ela deu.
 */
enum IaGrauEdicao: string implements HasLabel
{
    case SemEdicao = 'sem_edicao';
    case Leve = 'leve';
    case Media = 'media';
    case Refeita = 'refeita';

    public function getLabel(): string
    {
        return match ($this) {
            self::SemEdicao => 'Aprovado sem editar',
            self::Leve => 'Ajuste leve',
            self::Media => 'Ajuste grande',
            self::Refeita => 'Refeita do zero',
        };
    }

    public function score(): float
    {
        return match ($this) {
            self::SemEdicao => 1.0,
            self::Leve => 0.8,
            self::Media => 0.4,
            self::Refeita => 0.0,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SemEdicao => 'success',
            self::Leve => 'info',
            self::Media => 'warning',
            self::Refeita => 'danger',
        };
    }
}
