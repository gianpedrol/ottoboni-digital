<?php

namespace App\Services\Ia;

use App\Enums\IaApprovalStatus;
use App\Enums\IaGrauEdicao;
use App\Enums\IaIntent;
use App\Jobs\EnviarRespostaAprovadaJob;
use App\Models\AuditLog;
use App\Models\IaApproval;
use App\Models\IaCard;
use App\Models\IaExample;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A ação humana: aprovar (com ou sem edição) ou rejeitar um item da fila.
 *
 * Três coisas acontecem juntas e por isso vão numa transação:
 *   1. o item é medido e fechado
 *   2. o aprendizado é gravado (exemplo e, quando era "não sei", card novo)
 *   3. o envio é despachado para o n8n
 *
 * Sem aceite de responsabilidade nada disso roda — a regra é da camada de
 * serviço, não do formulário, para não existir caminho que passe por fora.
 */
class AprovadorDeResposta
{
    public function __construct(private readonly MedidorDeEdicao $medidor) {}

    public function aprovar(
        IaApproval $item,
        ?string $comentario,
        ?string $dm,
        User $autor,
        bool $aceite,
        ?string $observacao = null,
        bool $virarExemplo = true,
    ): IaApproval {
        if (! $aceite) {
            throw new RuntimeException('É obrigatório aceitar a responsabilidade pela resposta enviada.');
        }

        if ($item->status !== IaApprovalStatus::Pendente) {
            throw new RuntimeException(
                "Este item já está em \"{$item->status->getLabel()}\" — não pode ser aprovado de novo."
            );
        }

        // Campo em branco significa "manda o rascunho como está".
        $finalComentario = $this->limpar($comentario) ?? $item->rascunho_comentario;
        $finalDm = $this->limpar($dm) ?? $item->rascunho_dm;

        if (blank($finalComentario) && blank($finalDm)) {
            throw new RuntimeException('Não há texto para enviar. Use "Rejeitar" se a resposta certa é o silêncio.');
        }

        $medida = $this->medidor->medir(
            trim((string) $item->rascunho_comentario . ' ' . (string) $item->rascunho_dm),
            trim((string) $finalComentario . ' ' . (string) $finalDm),
        );

        DB::transaction(function () use ($item, $finalComentario, $finalDm, $autor, $observacao, $medida, $virarExemplo): void {
            $item->update([
                'status' => IaApprovalStatus::Aprovado,
                'final_comentario' => $finalComentario,
                'final_dm' => $finalDm,
                'grau_edicao' => $medida['grau'],
                'similaridade' => $medida['similaridade'],
                'score' => $medida['score'],
                'revisado_por' => $autor->id,
                'revisado_em' => now(),
                'observacao_humano' => $observacao,
                'responsabilidade_aceita' => true,
            ]);

            AuditLog::registrar('ia.aprovou', [
                'approval_id' => $item->id,
                'agente' => $item->doctor?->agente,
                'intent' => $item->intent->value,
                'grau_edicao' => $medida['grau']->value,
                'similaridade' => $medida['similaridade'],
            ]);

            if ($virarExemplo) {
                $this->gravarAprendizado($item, $autor, $medida['grau']);
            }
        });

        // Fora da transação: o envio é I/O e não deve prender o banco.
        EnviarRespostaAprovadaJob::dispatch($item->id);

        return $item->refresh();
    }

    public function rejeitar(IaApproval $item, User $autor, string $motivo): IaApproval
    {
        if ($item->status !== IaApprovalStatus::Pendente) {
            throw new RuntimeException(
                "Este item já está em \"{$item->status->getLabel()}\" — não pode ser rejeitado."
            );
        }

        $item->update([
            'status' => IaApprovalStatus::Rejeitado,
            'grau_edicao' => IaGrauEdicao::Refeita,
            'similaridade' => 0,
            'score' => 0,
            'revisado_por' => $autor->id,
            'revisado_em' => now(),
            'observacao_humano' => $motivo,
        ]);

        AuditLog::registrar('ia.rejeitou', [
            'approval_id' => $item->id,
            'agente' => $item->doctor?->agente,
            'intent' => $item->intent->value,
            'motivo' => $motivo,
        ]);

        return $item->refresh();
    }

    /**
     * Grava o que a agente tem que aprender com esta revisão.
     *
     * Só vira exemplo quando houve correção — aprovar 200 respostas idênticas
     * sem editar não ensina nada e só incharia o prompt. Já o "não sei" sempre
     * gera exemplo E card: é exatamente o buraco da base sendo tapado.
     */
    private function gravarAprendizado(IaApproval $item, User $autor, IaGrauEdicao $grau): void
    {
        $eraNaoSei = $item->intent === IaIntent::NaoSei;

        if ($grau === IaGrauEdicao::SemEdicao && ! $eraNaoSei) {
            return;
        }

        $pergunta = trim($item->textoDaPessoa());
        $resposta = trim((string) ($item->final_dm ?? $item->final_comentario));

        if ($pergunta === '' || $resposta === '') {
            return;
        }

        IaExample::query()->create([
            'doctor_id' => $item->doctor_id,
            'intent' => $item->intent,
            'canal' => $item->canal->value,
            'pergunta' => $pergunta,
            'resposta' => $resposta,
            'resposta_rejeitada' => $this->limpar(
                (string) ($item->rascunho_dm ?? $item->rascunho_comentario)
            ),
            'approval_id' => $item->id,
            'prioridade' => $eraNaoSei ? 10 : 0,
            'criado_por' => $autor->id,
        ]);

        if (! $eraNaoSei) {
            return;
        }

        // A resposta do humano entra na base como card validado: na próxima vez
        // a agente já sabe e não precisa escalar de novo.
        IaCard::query()->create([
            'doctor_id' => $item->doctor_id,
            'pergunta' => $pergunta,
            'resposta' => $resposta,
            'status' => 'validado',
            'origem' => 'painel_aprovacao',
            'approval_id' => $item->id,
            'criado_por' => $autor->id,
        ]);
    }

    private function limpar(?string $texto): ?string
    {
        $t = trim((string) $texto);

        return $t === '' ? null : $t;
    }
}
