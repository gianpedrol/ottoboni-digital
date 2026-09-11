<?php

namespace App\Http\Controllers\Ia;

use App\Enums\IaApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\IaApproval;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resultado do envio, vindo do workflow "IA ENVIO APROVADO".
 * Idempotente por approval_id: callback repetido não mexe em nada.
 */
class EnvioCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'approval_id' => ['required', 'integer'],
            'status' => ['required', 'in:enviado,erro'],
            'comentario_enviado' => ['nullable', 'boolean'],
            'dm_enviado' => ['nullable', 'boolean'],
            'erro' => ['nullable', 'string'],
        ]);

        WebhookLog::query()->create([
            'direcao' => 'in',
            'payload' => $dados,
            'assinatura_ok' => true,
            'status' => null,
            'created_at' => now(),
        ]);

        /** @var IaApproval|null $item */
        $item = IaApproval::query()->find($dados['approval_id']);

        if ($item === null) {
            return response()->json(['erro' => 'aprovação desconhecida'], 404);
        }

        if ($item->status !== IaApprovalStatus::Aprovado) {
            return response()->json(['ok' => true, 'ignorado' => 'item já resolvido']);
        }

        if ($dados['status'] === 'enviado') {
            $item->update([
                'status' => IaApprovalStatus::Enviado,
                'enviado_em' => now(),
                'erro' => $dados['erro'] ?? null,
            ]);
        } else {
            $item->update([
                'status' => IaApprovalStatus::Erro,
                'erro' => $dados['erro'] ?? 'erro não informado pelo n8n',
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
