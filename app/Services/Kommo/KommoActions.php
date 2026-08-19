<?php

namespace App\Services\Kommo;

use Carbon\CarbonInterface;

/**
 * Escritas no Kommo usadas pelo motor de follow-up (Fase 2).
 * Leituras ficam no LeadRepository; aqui só o que cria coisas.
 */
class KommoActions
{
    public function __construct(private readonly KommoClient $client) {}

    /**
     * Cria uma tarefa no lead para atendimento humano, com o texto sugerido.
     * Devolve o id da tarefa criada.
     */
    public function criarTarefa(int $leadId, string $texto, CarbonInterface $prazo, ?int $responsavelId = null): ?int
    {
        $payload = [
            'entity_id' => $leadId,
            'entity_type' => 'leads',
            'text' => $texto,
            'complete_till' => $prazo->getTimestamp(),
        ];

        if ($responsavelId !== null) {
            $payload['responsible_user_id'] = $responsavelId;
        }

        $body = $this->client->post('/tasks', [$payload]);

        return isset($body['_embedded']['tasks'][0]['id'])
            ? (int) $body['_embedded']['tasks'][0]['id']
            : null;
    }

    /**
     * Dispara um Salesbot para o lead (a API devolve 202 sem corpo).
     */
    public function dispararSalesbot(int $botId, int $leadId): void
    {
        $this->client->post("/bots/{$botId}/run", [
            'entity_id' => $leadId,
            'entity_type' => 'leads',
        ]);
    }
}
