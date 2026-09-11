<?php

namespace App\Services\Agenda;

use App\Models\Appointment;

final readonly class ResultadoDaAgenda
{
    public function __construct(
        public Appointment $agendamento,
        public ?EfeitoKommo $efeito = null,
        public ?Appointment $anterior = null,
    ) {}
}
