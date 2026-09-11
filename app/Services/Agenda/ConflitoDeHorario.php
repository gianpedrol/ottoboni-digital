<?php

namespace App\Services\Agenda;

use App\Models\Appointment;
use RuntimeException;

class ConflitoDeHorario extends RuntimeException
{
    public function __construct(public readonly Appointment $existente)
    {
        $existente->loadMissing(['patient', 'doctor']);

        parent::__construct(sprintf(
            '%s já tem %s das %s às %s (%s) em %s. Escolha outro horário.',
            $existente->doctor->nome ?? 'O médico',
            $existente->patient->nome ?? 'um paciente',
            $existente->inicioLocal()->format('H:i'),
            $existente->fimLocal()->format('H:i'),
            mb_strtolower($existente->tipo->getLabel()),
            $existente->inicioLocal()->format('d/m/Y'),
        ));
    }
}
