<?php

namespace App\Services\Followup;

use App\Enums\FollowupRunStatus;
use App\Models\FollowupRun;

/**
 * Cancela os passos seguintes de um lead numa régua, com o motivo
 * registrado. Usado quando o lead responde, ganha/perde, ou quando um
 * humano assume o atendimento.
 */
class CanceladorDeSeguintes
{
    /**
     * Cancela todos os runs AGENDADOS do mesmo plano e lead a partir da
     * ordem do run informado (inclusive, se ainda estiver agendado).
     *
     * @return int quantos runs foram cancelados
     */
    public function cancelar(FollowupRun $referencia, string $motivo, bool $incluirReferencia = true): int
    {
        $ordemReferencia = $referencia->step->ordem;

        $query = FollowupRun::query()
            ->where('plan_id', $referencia->plan_id)
            ->where('lead_id_kommo', $referencia->lead_id_kommo)
            ->where('status', FollowupRunStatus::Agendado)
            ->whereHas('step', function ($q) use ($ordemReferencia, $incluirReferencia): void {
                $incluirReferencia
                    ? $q->where('ordem', '>=', $ordemReferencia)
                    : $q->where('ordem', '>', $ordemReferencia);
            });

        $cancelados = 0;

        foreach ($query->get() as $run) {
            $run->update([
                'status' => FollowupRunStatus::Cancelado,
                'motivo_cancelamento' => $motivo,
            ]);

            $cancelados++;
        }

        return $cancelados;
    }

    /**
     * Marca o run como respondido e cancela todos os seguintes.
     */
    public function marcarRespondido(FollowupRun $run): void
    {
        $run->update(['status' => FollowupRunStatus::Respondido]);

        $this->cancelar($run, 'lead respondeu', incluirReferencia: false);
    }
}
