<?php

namespace App\Http\Controllers\Ia;

use App\Enums\IaIntent;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\Ia\EnfileiradorDeRascunho;
use App\Services\Ia\PortaoDeAprovacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * O ponto central: o n8n manda o rascunho que a agente gerou e recebe de volta
 * a ordem — envia ou não envia.
 *
 * Contrato em docs/n8n-treinamento-ia.md. Qualquer falha aqui responde
 * enviar_direto = false: na dúvida, a agente fica calada.
 */
class RascunhoController extends Controller
{
    public function __invoke(
        Request $request,
        PortaoDeAprovacao $portao,
        EnfileiradorDeRascunho $enfileirador,
    ): JsonResponse {
        $dados = $request->validate([
            'agente' => ['required', 'string', 'max:20'],
            'canal' => ['required', 'in:comentario,direct'],
            'intent' => ['required', 'string', 'max:30'],
            'confianca' => ['nullable', 'numeric', 'between:0,1'],

            'ig_id' => ['nullable', 'string', 'max:64'],
            'ig_username' => ['nullable', 'string', 'max:120'],
            'post_id' => ['nullable', 'string', 'max:64'],
            'post_permalink' => ['nullable', 'string', 'max:255'],
            'post_caption' => ['nullable', 'string'],
            'post_media_url' => ['nullable', 'string', 'max:1024'],
            'comment_id' => ['nullable', 'string', 'max:64'],
            'comentario_texto' => ['nullable', 'string'],
            'comentario_em' => ['nullable', 'date'],
            'mensagem_texto' => ['nullable', 'string'],
            'historico' => ['nullable', 'array'],

            'rascunho_comentario' => ['nullable', 'string'],
            'rascunho_dm' => ['nullable', 'string'],

            'modelo' => ['nullable', 'string', 'max:60'],
            'prompt_version_id' => ['nullable', 'integer'],
            'cards_usados' => ['nullable', 'array'],
            'materiais' => ['nullable', 'array', 'max:10'],
            'materiais.*' => ['string', 'max:60'],
            'tokens_prompt' => ['nullable', 'integer'],
            'tokens_resposta' => ['nullable', 'integer'],

            // O n8n avisa quando já enviou (caminho liberado por acurácia).
            'ja_enviado' => ['nullable', 'boolean'],
        ]);

        $doctor = Doctor::query()->where('agente', $dados['agente'])->first();

        if ($doctor === null) {
            return response()->json(['erro' => 'agente desconhecido'], 404);
        }

        $intent = IaIntent::tryFrom((string) $dados['intent']) ?? IaIntent::Indefinido;

        try {
            $decisao = $portao->decidir($doctor->id, $intent);
        } catch (Throwable $e) {
            report($e);

            // Portão indisponível não vira permissão para falar.
            return response()->json([
                'enviar_direto' => false,
                'motivo' => 'erro_base',
                'erro' => 'falha ao avaliar o portão',
            ], 200);
        }

        if ($decisao->enviarDireto) {
            $item = $dados['ja_enviado'] ?? false
                ? $enfileirador->registrarAutomatico($doctor, $dados, $decisao)
                : null;

            return response()->json([
                ...$decisao->toArray(),
                'approval_id' => $item?->id,
            ]);
        }

        $item = $enfileirador->enfileirar($doctor, $dados, $decisao);

        return response()->json([
            ...$decisao->toArray(),
            'approval_id' => $item->id,
            'ja_na_fila' => $item->wasRecentlyCreated === false,
            'enviar_dm_espera' => $decisao->dmEspera && ! $item->dm_espera_enviada,
        ]);
    }
}
