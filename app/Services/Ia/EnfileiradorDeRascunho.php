<?php

namespace App\Services\Ia;

use App\Enums\IaApprovalStatus;
use App\Enums\IaCanal;
use App\Enums\IaIntent;
use App\Enums\IaMotivoFila;
use App\Models\Doctor;
use App\Models\IaApproval;
use App\Services\Ia\DTO\DecisaoDoPortao;

/**
 * Grava na fila o rascunho que o n8n gerou e que não pode ser enviado sozinho.
 *
 * Idempotente por comment_id: o webhook da Meta repete evento com frequência e
 * o mesmo comentário não pode aparecer duas vezes para a equipe revisar.
 */
class EnfileiradorDeRascunho
{
    public function __construct(private readonly NotificadorDeFila $notificador) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public function enfileirar(Doctor $doctor, array $dados, DecisaoDoPortao $decisao): IaApproval
    {
        $commentId = $dados['comment_id'] ?? null;

        if (filled($commentId)) {
            $existente = IaApproval::query()->where('comment_id', $commentId)->first();

            if ($existente !== null) {
                return $existente;
            }
        }

        $intent = IaIntent::tryFrom((string) ($dados['intent'] ?? '')) ?? IaIntent::Indefinido;
        $canal = IaCanal::tryFrom((string) ($dados['canal'] ?? '')) ?? IaCanal::Comentario;

        /** @var IaApproval $item */
        $item = IaApproval::query()->create([
            'doctor_id' => $doctor->id,
            'canal' => $canal,
            'intent' => $intent,
            'confianca' => $dados['confianca'] ?? null,
            'motivo_fila' => $decisao->motivo,

            'ig_id' => $dados['ig_id'] ?? null,
            'ig_username' => $dados['ig_username'] ?? null,
            'post_id' => $dados['post_id'] ?? null,
            'post_permalink' => $dados['post_permalink'] ?? null,
            'post_caption' => $dados['post_caption'] ?? null,
            'post_media_url' => $dados['post_media_url'] ?? null,
            'comment_id' => $commentId,
            'comentario_texto' => $dados['comentario_texto'] ?? null,
            'comentario_em' => $dados['comentario_em'] ?? now(),
            'mensagem_texto' => $dados['mensagem_texto'] ?? null,
            'historico' => $dados['historico'] ?? [],

            'rascunho_comentario' => $dados['rascunho_comentario'] ?? null,
            'rascunho_dm' => $dados['rascunho_dm'] ?? null,

            'modelo' => $dados['modelo'] ?? $decisao->modelo,
            'prompt_version_id' => $dados['prompt_version_id'] ?? null,
            'cards_usados' => $dados['cards_usados'] ?? [],
            'materiais' => self::codigos($dados['materiais'] ?? []),
            'tokens_prompt' => $dados['tokens_prompt'] ?? null,
            'tokens_resposta' => $dados['tokens_resposta'] ?? null,

            'status' => IaApprovalStatus::Pendente,
            'expira_em' => now()->addMinutes($decisao->timeoutMin),
        ]);

        // "Não soube responder" e falha de base avisam na hora; o resto entra
        // no resumo do vigia, para não transformar a fila em spam.
        if (in_array($decisao->motivo, [IaMotivoFila::NaoSei, IaMotivoFila::ErroBase], true)) {
            $this->notificador->urgente($item);
        }

        return $item;
    }

    /**
     * Registra o que saiu automático. Não entra na acurácia (ninguém revisou),
     * mas o histórico do painel precisa mostrar tudo que a agente falou.
     *
     * @param  array<string, mixed>  $dados
     */
    public function registrarAutomatico(Doctor $doctor, array $dados, DecisaoDoPortao $decisao): IaApproval
    {
        $commentId = $dados['comment_id'] ?? null;

        if (filled($commentId)) {
            $existente = IaApproval::query()->where('comment_id', $commentId)->first();

            if ($existente !== null) {
                return $existente;
            }
        }

        /** @var IaApproval $item */
        $item = IaApproval::query()->create([
            'doctor_id' => $doctor->id,
            'canal' => IaCanal::tryFrom((string) ($dados['canal'] ?? '')) ?? IaCanal::Comentario,
            'intent' => IaIntent::tryFrom((string) ($dados['intent'] ?? '')) ?? IaIntent::Indefinido,
            'confianca' => $dados['confianca'] ?? null,
            'motivo_fila' => IaMotivoFila::Auto,
            'ig_id' => $dados['ig_id'] ?? null,
            'ig_username' => $dados['ig_username'] ?? null,
            'post_id' => $dados['post_id'] ?? null,
            'post_permalink' => $dados['post_permalink'] ?? null,
            'comment_id' => $commentId,
            'comentario_texto' => $dados['comentario_texto'] ?? null,
            'mensagem_texto' => $dados['mensagem_texto'] ?? null,
            'rascunho_comentario' => $dados['rascunho_comentario'] ?? null,
            'rascunho_dm' => $dados['rascunho_dm'] ?? null,
            'final_comentario' => $dados['rascunho_comentario'] ?? null,
            'final_dm' => $dados['rascunho_dm'] ?? null,
            'modelo' => $dados['modelo'] ?? $decisao->modelo,
            'prompt_version_id' => $dados['prompt_version_id'] ?? null,
            'cards_usados' => $dados['cards_usados'] ?? [],
            'materiais' => self::codigos($dados['materiais'] ?? []),
            'final_materiais' => self::codigos($dados['materiais'] ?? []),
            'status' => IaApprovalStatus::AutoEnviado,
            'enviado_em' => now(),
        ]);

        return $item;
    }

    /**
     * Códigos de material como a agente devolveu: só strings, sem repetição.
     *
     * @return array<int, string>
     */
    public static function codigos(mixed $lista): array
    {
        if (! is_array($lista)) {
            return [];
        }

        $codigos = [];

        foreach ($lista as $c) {
            if (is_string($c) && $c !== '' && ! in_array($c, $codigos, true)) {
                $codigos[] = $c;
            }
        }

        return array_slice($codigos, 0, 10);
    }
}
