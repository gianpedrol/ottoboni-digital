<?php

namespace App\Services\Followup;

use App\Enums\FollowupCanal;
use App\Enums\FollowupGatilho;
use App\Enums\FollowupModo;
use App\Enums\FollowupRunStatus;
use App\Enums\OrigemTexto;
use App\Models\FollowupMessage;
use App\Models\FollowupRun;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\DTO\TaskData;
use App\Services\Kommo\KommoActions;
use App\Services\Kommo\KommoException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Executa um followup_run. Todas as verificações de cancelamento rodam
 * IMEDIATAMENTE antes do envio, não só no agendamento (seção 9.2).
 *
 * Roteamento:
 * - texto fixo + tarefa Kommo ou Salesbot → direto na API do Kommo
 *   (o caminho seguro da Fase 2);
 * - qualquer coisa com IA, Instagram ou canal automático → webhook n8n
 *   (contrato 9.3), que resolve o canal e devolve o resultado no callback.
 */
class ExecutorDeFollowup
{
    public function __construct(
        private readonly LeadRepository $repository,
        private readonly KommoActions $kommo,
        private readonly N8nWebhookClient $n8n,
        private readonly CanceladorDeSeguintes $cancelador,
    ) {}

    public function executar(FollowupRun $run): void
    {
        // Idempotência: só executa o que ainda está agendado.
        if ($run->status !== FollowupRunStatus::Agendado) {
            return;
        }

        $step = $run->step;
        $plan = $run->plan;

        if ($plan === null || $step === null || ! $plan->ativo || ! $step->ativo) {
            $this->cancelar($run, 'régua ou passo desativado');

            return;
        }

        try {
            $lead = $this->repository->find($run->lead_id_kommo);
        } catch (KommoException $e) {
            $this->falhar($run, "Kommo indisponível na verificação: {$e->getMessage()}");

            return;
        }

        if ($lead === null) {
            $this->cancelar($run, 'lead não existe mais no Kommo');

            return;
        }

        // ── Cancelamento automático, verificado na hora do envio ─────
        if ($lead->ganho() || $lead->perdido()) {
            $this->cancelador->cancelar($run, $lead->ganho() ? 'lead ganhou' : 'lead perdido');

            return;
        }

        if ($this->temTarefaHumanaAberta($run->lead_id_kommo)) {
            $this->cancelador->cancelar($run, 'tarefa humana aberta no lead');

            return;
        }

        if ($plan->gatilho === FollowupGatilho::Etapa
            && $lead->statusId !== (int) ($plan->gatilho_config['status_id'] ?? 0)) {
            $this->cancelador->cancelar($run, 'lead saiu da etapa do gatilho');

            return;
        }

        // ── Janela de horário: fora dela, adia (não falha) ───────────
        if (! JanelaDeEnvio::dentro(now())) {
            $run->update(['agendado_para' => JanelaDeEnvio::proxima(now())]);

            return;
        }

        // ── Limites de segurança ─────────────────────────────────────
        $enviadosTotal = FollowupRun::query()
            ->where('lead_id_kommo', $run->lead_id_kommo)
            ->whereIn('status', [FollowupRunStatus::Enviado, FollowupRunStatus::Respondido])
            ->count();

        if ($enviadosTotal >= (int) config('painel.followup.max_por_lead_total')) {
            $this->cancelador->cancelar($run, 'limite total de follow-ups do lead atingido');

            return;
        }

        $enviadoNasUltimas24h = FollowupRun::query()
            ->where('lead_id_kommo', $run->lead_id_kommo)
            ->whereIn('status', [FollowupRunStatus::Enviado, FollowupRunStatus::Respondido])
            ->where('executado_em', '>=', now()->subHours(24 / (int) config('painel.followup.max_por_lead_24h')))
            ->exists();

        if ($enviadoNasUltimas24h) {
            $run->update(['agendado_para' => JanelaDeEnvio::proxima(now()->addHours(24)->toImmutable())]);

            return;
        }

        // ── Roteamento ───────────────────────────────────────────────
        $texto = TextoRenderer::render((string) $step->texto, $lead);

        $direto = $step->modo === FollowupModo::TextoFixo
            && in_array($step->canal, [FollowupCanal::KommoTask, FollowupCanal::KommoBot], true);

        try {
            $direto
                ? $this->executarDireto($run, $lead, $texto)
                : $this->executarViaN8n($run, $lead, $texto);
        } catch (Throwable $e) {
            $this->falhar($run, $e->getMessage());
        }
    }

    private function executarDireto(FollowupRun $run, LeadData $lead, string $texto): void
    {
        $step = $run->step;

        $botId = $step->kommo_bot_id ?? $run->doctor?->kommo_bot_id;

        if ($step->canal === FollowupCanal::KommoBot && $botId !== null) {
            $this->kommo->dispararSalesbot($botId, $lead->id);
            $canalUsado = FollowupCanal::KommoBot;
        } else {
            // Sem bot configurado, o degrau seguro é tarefa humana.
            $prazo = JanelaDeEnvio::proxima(now()->addHours(4)->toImmutable());
            $this->kommo->criarTarefa($lead->id, $texto, $prazo, $lead->responsibleUserId);
            $canalUsado = FollowupCanal::KommoTask;
        }

        $run->update([
            'status' => FollowupRunStatus::Enviado,
            'executado_em' => now(),
            'canal_usado' => $canalUsado,
        ]);

        FollowupMessage::query()->create([
            'run_id' => $run->id,
            'canal' => $canalUsado,
            'texto_final' => $texto,
            'origem_texto' => OrigemTexto::Fixo,
        ]);
    }

    private function executarViaN8n(FollowupRun $run, LeadData $lead, string $texto): void
    {
        $step = $run->step;
        $doctor = $run->doctor;

        $payload = [
            'run_id' => $run->id,
            'conta' => $doctor->agente,
            'lead_id' => $lead->id,
            'ig_username' => ltrim((string) $lead->instagramHandle(), '@') ?: null,
            'canal' => $step->canal->value,
            'modo' => $step->modo->value,
            'texto' => $texto,
            'prompt_ia' => $step->prompt_ia,
            'contexto' => [
                'procedimento' => $lead->procedimento,
                'temperatura' => $lead->temperatura,
                'ultima_etapa' => $this->nomeDaEtapa($lead),
                'dias_sem_resposta' => $lead->updatedAt !== null
                    ? (int) $lead->updatedAt->diffInDays(now(), true)
                    : null,
            ],
            'callback_url' => route('followup.callback'),
            'enviado_em' => CarbonImmutable::now(config('painel.timezone'))->toIso8601String(),
        ];

        $this->n8n->enviar($payload);

        // 202 aceito: o canal final e o texto definitivo chegam no callback.
        $run->update([
            'status' => FollowupRunStatus::Enviado,
            'executado_em' => now(),
        ]);

        FollowupMessage::query()->create([
            'run_id' => $run->id,
            'canal' => $step->canal,
            'texto_final' => $texto,
            'origem_texto' => $step->modo === FollowupModo::Ia ? OrigemTexto::Template : OrigemTexto::Fixo,
        ]);
    }

    private function temTarefaHumanaAberta(int $leadId): bool
    {
        try {
            return $this->repository->openTasks($leadId)
                ->contains(fn (TaskData $t): bool => ! $t->isCompleted);
        } catch (KommoException) {
            // Sem resposta do Kommo, não bloqueia por isso — as demais
            // verificações (status do lead) já falharam antes se a API caiu.
            return false;
        }
    }

    private function nomeDaEtapa(LeadData $lead): ?string
    {
        try {
            return $this->repository->pipelines()
                ->first(fn ($p): bool => $p->id === $lead->pipelineId)
                ?->statusName($lead->statusId);
        } catch (KommoException) {
            return null;
        }
    }

    private function cancelar(FollowupRun $run, string $motivo): void
    {
        $run->update([
            'status' => FollowupRunStatus::Cancelado,
            'motivo_cancelamento' => $motivo,
        ]);
    }

    private function falhar(FollowupRun $run, string $erro): void
    {
        $run->update([
            'status' => FollowupRunStatus::Falhou,
            'executado_em' => now(),
        ]);

        FollowupMessage::query()->create([
            'run_id' => $run->id,
            'canal' => $run->step->canal,
            'texto_final' => (string) $run->step->texto,
            'origem_texto' => OrigemTexto::Fixo,
            'erro' => mb_substr($erro, 0, 1000),
        ]);
    }
}
