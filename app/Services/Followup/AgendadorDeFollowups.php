<?php

namespace App\Services\Followup;

use App\Enums\FollowupGatilho;
use App\Enums\FollowupRunStatus;
use App\Models\FollowupPlan;
use App\Models\FollowupRun;
use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Varre os leads que casam com o gatilho de uma régua ativa e cria os
 * followup_runs. Idempotente: a chave única (plan_id, step_id,
 * lead_id_kommo) garante que reprocessar nunca agenda duas vezes.
 */
class AgendadorDeFollowups
{
    /**
     * Quantos dias para trás procurar leads que casam com o gatilho,
     * quando a régua não define o próprio período.
     */
    private const PERIODO_PADRAO_DIAS = 60;

    public function __construct(private readonly LeadRepository $repository) {}

    /**
     * @return int quantos runs novos foram agendados
     */
    public function agendarParaPlano(FollowupPlan $plan): int
    {
        if (! $plan->ativo || $plan->gatilho === FollowupGatilho::Manual) {
            return 0;
        }

        $leads = $this->leadsQueCasam($plan);

        $novos = 0;

        foreach ($leads as $lead) {
            $novos += $this->agendarParaLead($plan, $lead);
        }

        return $novos;
    }

    /**
     * Agenda todos os passos ativos da régua para um lead (usado pelo
     * gatilho manual da tela de Atendimentos e pela varredura).
     *
     * @return int quantos runs novos foram criados
     */
    public function agendarParaLead(FollowupPlan $plan, LeadData $lead): int
    {
        // Leads fechados não entram em régua nenhuma.
        if ($lead->ganho() || $lead->perdido()) {
            return 0;
        }

        // Limite de segurança: máximo de follow-ups por lead, contando
        // o que já foi enviado e o que está na fila.
        $existentes = FollowupRun::query()
            ->where('lead_id_kommo', $lead->id)
            ->whereIn('status', [
                FollowupRunStatus::Agendado,
                FollowupRunStatus::Enviado,
                FollowupRunStatus::Respondido,
            ])
            ->count();

        $maximo = (int) config('painel.followup.max_por_lead_total');

        $gatilhoEm = CarbonImmutable::now();

        $novos = 0;

        foreach ($plan->steps()->where('ativo', true)->get() as $step) {
            if ($existentes + $novos >= $maximo) {
                break;
            }

            $agendadoPara = JanelaDeEnvio::proxima($gatilhoEm->addHours($step->offset_horas));

            try {
                FollowupRun::query()->create([
                    'plan_id' => $plan->id,
                    'step_id' => $step->id,
                    'lead_id_kommo' => $lead->id,
                    'doctor_id' => $plan->doctor_id,
                    'status' => FollowupRunStatus::Agendado,
                    'agendado_para' => $agendadoPara,
                ]);

                $novos++;
            } catch (UniqueConstraintViolationException) {
                // Já agendado por uma varredura anterior — idempotência.
            }
        }

        return $novos;
    }

    /**
     * @return Collection<int, LeadData>
     */
    private function leadsQueCasam(FollowupPlan $plan): Collection
    {
        $config = $plan->gatilho_config ?? [];
        $dias = (int) ($config['periodo_dias'] ?? self::PERIODO_PADRAO_DIAS);

        $tz = config('painel.timezone');

        $filters = new LeadFilters(
            pipelineIds: [(int) $plan->doctor->kommo_pipeline_id],
            from: CarbonImmutable::now($tz)->subDays($dias)->startOfDay()->utc(),
            to: CarbonImmutable::now($tz)->endOfDay()->utc(),
        );

        $leads = $this->repository->leads($filters)
            ->reject(fn (LeadData $l): bool => $l->ganho() || $l->perdido());

        return match ($plan->gatilho) {
            FollowupGatilho::Etapa => $leads->filter(
                fn (LeadData $l): bool => $l->statusId === (int) ($config['status_id'] ?? 0),
            ),
            FollowupGatilho::Temperatura => $leads->filter(
                fn (LeadData $l): bool => mb_strtolower(trim((string) $l->temperatura))
                    === mb_strtolower(trim((string) ($config['temperatura'] ?? ''))),
            ),
            FollowupGatilho::SemResposta => $leads->filter(
                fn (LeadData $l): bool => $l->updatedAt !== null
                    && $l->updatedAt->lt(now()->subHours((int) ($config['horas'] ?? 48))),
            ),
            default => collect(),
        };
    }
}
