<?php

namespace App\Http\Controllers;

use App\Enums\FollowupCanal;
use App\Enums\FollowupRunStatus;
use App\Enums\OrigemTexto;
use App\Models\FollowupRun;
use App\Models\WebhookLog;
use App\Services\Followup\CanceladorDeSeguintes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callback do FOLLOWUP EXECUTOR (n8n) com o resultado do envio.
 * Contrato da seção 9.3: assinado com HMAC, idempotente por run_id.
 * Sem assinatura válida, 401 — sempre.
 */
class FollowupCallbackController extends Controller
{
    public function __invoke(Request $request, CanceladorDeSeguintes $cancelador): JsonResponse
    {
        $corpo = $request->getContent();
        $assinaturaRecebida = (string) $request->header('X-Signature', '');
        $secret = (string) config('painel.n8n.webhook_secret');

        $assinaturaEsperada = 'sha256=' . hash_hmac('sha256', $corpo, $secret);

        $valida = $secret !== '' && hash_equals($assinaturaEsperada, $assinaturaRecebida);

        WebhookLog::query()->create([
            'direcao' => 'in',
            'payload' => $request->json()->all() ?: ['corpo_invalido' => mb_substr($corpo, 0, 500)],
            'assinatura_ok' => $valida,
            'status' => $valida ? null : 'assinatura_invalida',
            'created_at' => now(),
        ]);

        if (! $valida) {
            return response()->json(['erro' => 'assinatura inválida'], 401);
        }

        $dados = $request->validate([
            'run_id' => ['required', 'integer'],
            'status' => ['required', 'in:enviado,falhou,fora_da_janela,cancelado'],
            'canal_usado' => ['nullable', 'in:instagram,kommo_bot,kommo_task'],
            'texto_final' => ['nullable', 'string'],
            'origem_texto' => ['nullable', 'in:ia_ancorada,template,fixo'],
            'erro' => ['nullable', 'string'],
        ]);

        $run = FollowupRun::query()->find($dados['run_id']);

        if ($run === null) {
            return response()->json(['erro' => 'run desconhecido'], 404);
        }

        // Idempotência por run_id: o primeiro callback resolve o run
        // (preenche canal_usado ou muda o status); os repetidos só são
        // registrados no log e não mexem em nada.
        $jaResolvido = $run->status !== FollowupRunStatus::Enviado || $run->canal_usado !== null;

        if ($jaResolvido) {
            return response()->json(['ok' => true, 'ignorado' => 'run já resolvido']);
        }

        $mensagem = $run->messages()->latest()->first();

        if ($dados['status'] === 'enviado') {
            $run->update([
                'canal_usado' => FollowupCanal::from($dados['canal_usado'] ?? FollowupCanal::KommoTask->value),
            ]);
        } elseif ($dados['status'] === 'cancelado') {
            $run->update([
                'status' => FollowupRunStatus::Cancelado,
                'motivo_cancelamento' => 'cancelado pelo n8n',
            ]);

            $cancelador->cancelar($run, 'cancelado pelo n8n');
        } else {
            // falhou ou fora_da_janela
            $run->update([
                'status' => FollowupRunStatus::Falhou,
                'motivo_cancelamento' => $dados['status'] === 'fora_da_janela'
                    ? 'fora da janela de 24h do Instagram'
                    : null,
            ]);
        }

        if ($mensagem !== null) {
            $mensagem->update([
                'canal' => filled($dados['canal_usado'] ?? null)
                    ? FollowupCanal::from($dados['canal_usado'])
                    : $mensagem->canal,
                'texto_final' => $dados['texto_final'] ?? $mensagem->texto_final,
                'origem_texto' => filled($dados['origem_texto'] ?? null)
                    ? OrigemTexto::from($dados['origem_texto'])
                    : $mensagem->origem_texto,
                'resposta_n8n' => $dados,
                'erro' => $dados['erro'] ?? null,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
