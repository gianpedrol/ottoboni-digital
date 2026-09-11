<?php

namespace App\Services\Ia;

use App\Models\WebhookLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fala com o workflow "IA ENVIO APROVADO" do n8n, assinado com HMAC-SHA256 —
 * o mesmo contrato do FOLLOWUP EXECUTOR.
 *
 * O painel nunca toca no token do Instagram: quem envia é sempre o n8n.
 */
class IaWebhookClient
{
    public static function configurado(): bool
    {
        return filled(config('painel.ia.envio_url'))
            && filled(config('painel.ia.webhook_secret'));
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     */
    public function enviar(array $payload): void
    {
        $url = config('painel.ia.envio_url');
        $secret = config('painel.ia.webhook_secret');

        if (blank($url) || blank($secret)) {
            throw new RuntimeException(
                'Webhook de envio da IA não configurado (IA_ENVIO_URL / IA_WEBHOOK_SECRET no .env).'
            );
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $assinatura = 'sha256=' . hash_hmac('sha256', (string) $body, (string) $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature' => $assinatura,
            ])
                ->timeout((int) config('painel.ia.timeout'))
                ->withBody((string) $body, 'application/json')
                ->post((string) $url);
        } catch (ConnectionException $e) {
            $this->log($payload, 'sem_conexao');

            throw new RuntimeException("n8n inacessível: {$e->getMessage()}", 0, $e);
        }

        $this->log($payload, (string) $response->status());

        if (! in_array($response->status(), [200, 202], true)) {
            throw new RuntimeException(
                "n8n devolveu {$response->status()} para a aprovação {$payload['approval_id']}."
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
