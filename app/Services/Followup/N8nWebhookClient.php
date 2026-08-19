<?php

namespace App\Services\Followup;

use App\Models\WebhookLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envia o payload de follow-up para o workflow FOLLOWUP EXECUTOR no n8n,
 * assinado com HMAC-SHA256 (contrato da seção 9.3 — não mudar sem conversar).
 */
class N8nWebhookClient
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException quando o n8n não está configurado ou não aceita
     */
    public function enviar(array $payload): void
    {
        $url = config('painel.n8n.followup_url');
        $secret = config('painel.n8n.webhook_secret');

        if (blank($url) || blank($secret)) {
            throw new RuntimeException(
                'Webhook do n8n não configurado (N8N_FOLLOWUP_URL / N8N_WEBHOOK_SECRET no .env).'
            );
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $assinatura = 'sha256=' . hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature' => $assinatura,
            ])
                ->timeout((int) config('painel.n8n.timeout'))
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $e) {
            $this->log($payload, 'sem_conexao');

            throw new RuntimeException("n8n inacessível: {$e->getMessage()}", 0, $e);
        }

        $this->log($payload, (string) $response->status());

        // O contrato espera 202 (aceito para processar); 200 também serve.
        if (! in_array($response->status(), [200, 202], true)) {
            throw new RuntimeException(
                "n8n devolveu {$response->status()} para o run {$payload['run_id']}."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function log(array $payload, string $status): void
    {
        WebhookLog::query()->create([
            'direcao' => 'out',
            'payload' => $payload,
            'assinatura_ok' => true,
            'status' => $status,
            'created_at' => now(),
        ]);
    }
}
