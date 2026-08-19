<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowupGatilho: string implements HasLabel
{
    case Etapa = 'etapa';
    case Temperatura = 'temperatura';
    case SemResposta = 'sem_resposta';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Etapa => 'Entrou numa etapa',
            self::Temperatura => 'Temperatura do lead',
            self::SemResposta => 'Sem resposta há X horas',
            self::Manual => 'Agendamento manual',
        };
    }
}
