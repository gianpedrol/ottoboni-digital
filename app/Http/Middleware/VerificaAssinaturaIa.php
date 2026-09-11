<?php

namespace App\Http\Middleware;

use App\Models\WebhookLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Todo tráfego entre o n8n e a área de IA é assinado com HMAC-SHA256 do corpo
 * cru (mesmo contrato do FOLLOWUP EXECUTOR). Sem assinatura válida, 401 —
 * sempre, e a tentativa fica registrada no log de webhooks.
 */
class VerificaAssinaturaIa
{
    public function handle(Request $request, Closure $next): Response
    {
        $corpo = $request->getContent();
        $recebida = (string) $request->header('X-Signature', '');
        $secret = (string) config('painel.ia.webhook_secret');

        $esperada = 'sha256=' . hash_hmac('sha256', $corpo, $secret);
        $valida = $secret !== '' && hash_equals($esperada, $recebida);

        if (! $valida) {
            WebhookLog::query()->create([
                'direcao' => 'in',
                'payload' => ['rota' => $request->path(), 'corpo' => mb_substr($corpo, 0, 500)],
                'assinatura_ok' => false,
                'status' => 'assinatura_invalida',
                'created_at' => now(),
            ]);

            return response()->json(['erro' => 'assinatura inválida'], 401);
        }

        return $next($request);
    }
}
