<?php

namespace App\Jobs;

use App\Enums\IaApprovalStatus;
use App\Models\IaApproval;
use App\Services\Ia\IaWebhookClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Manda para o n8n a resposta que o humano aprovou. O status só vira "enviado"
 * quando o n8n confirma pelo callback — igual ao motor de follow-up.
 */
class EnviarRespostaAprovadaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $approvalId) {}

    public function handle(IaWebhookClient $client): void
    {
        /** @var IaApproval|null $item */
        $item = IaApproval::query()->with('doctor')->find($this->approvalId);

        if ($item === null || $item->status !== IaApprovalStatus::Aprovado) {
            return; // já resolvido ou apagado: nada a fazer
        }

        try {
            $client->enviar([
                'approval_id' => $item->id,
                'agente' => $item->doctor?->agente,
                'ig_user_id' => $item->doctor?->ig_user_id,
                'canal' => $item->canal->value,
                'ig_id' => $item->ig_id,
                'comment_id' => $item->comment_id,
                'ig_username' => $item->ig_username,
                'comentario' => $item->final_comentario,
                'dm' => $item->final_dm,
                // O n8n usa para ajustar a memória da conversa: o rascunho já
                // pode estar gravado no histórico como se tivesse sido enviado.
                'rascunho_dm' => $item->rascunho_dm,
                'mensagem_texto' => $item->mensagem_texto,
                'comentario_texto' => $item->comentario_texto,
                'intent' => $item->intent?->value,
            ]);
        } catch (RuntimeException $e) {
            // Na última tentativa o item fica visível como erro no painel,
            // em vez de morrer silenciosamente na fila.
            if ($this->attempts() >= $this->tries) {
                $item->update([
                    'status' => IaApprovalStatus::Erro,
                    'erro' => $e->getMessage(),
                ]);

                return;
            }

            throw $e;
        }
    }
}
