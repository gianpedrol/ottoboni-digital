<?php

namespace App\Services\Kommo;

use App\Models\KommoApiCall;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Único ponto do sistema que fala com a API do Kommo.
 *
 * - throttle central (KOMMO_RATE_PER_SECOND, margem sobre o limite de 7 req/s)
 * - retry com backoff exponencial em 429 e 5xx
 * - 403 interrompe tudo (possível bloqueio de IP) e loga em nível crítico
 * - paginação via generator respeitando limit=250
 * - toda chamada registrada em kommo_api_calls
 */
class KommoClient
{
    private const THROTTLE_KEY = 'kommo-api';

    public function __construct(
        private readonly ?string $token = null,
        private readonly ?string $baseUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('get', $endpoint, $query);
    }

    /**
     * Alguns endpoints do Kommo recebem um objeto, outros (como /tasks)
     * recebem uma LISTA de objetos — por isso o payload aceita os dois.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $payload
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $payload = []): array
    {
        return $this->request('post', $endpoint, $payload);
    }

    /**
     * Percorre todas as páginas de uma listagem e entrega os itens um a um.
     *
     * @param  array<string, mixed>  $query
     * @return Generator<int, array<string, mixed>>
     */
    public function paginate(string $endpoint, array $query, string $embeddedKey, ?int $maxPages = null): Generator
    {
        $page = 1;
        $limit = (int) config('kommo.page_limit');

        while (true) {
            $body = $this->get($endpoint, [...$query, 'page' => $page, 'limit' => $limit]);

            $items = $body['_embedded'][$embeddedKey] ?? [];

            foreach ($items as $item) {
                yield $item;
            }

            $hasNext = isset($body['_links']['next']) && $items !== [];

            if (! $hasNext) {
                break;
            }

            $page++;

            if ($maxPages !== null && $page > $maxPages) {
                Log::warning('Kommo: paginação interrompida no limite de páginas', [
                    'endpoint' => $endpoint,
                    'max_pages' => $maxPages,
                ]);

                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $data): array
    {
        $attempts = (int) config('kommo.retries');
        $attempt = 0;

        while (true) {
            $attempt++;
            $this->waitForSlot();

            $start = microtime(true);

            try {
                $pending = Http::withToken($this->token())
                    ->acceptJson()
                    ->timeout(30)
                    ->connectTimeout(10)
                    ->baseUrl($this->baseUrl());

                /** @var Response $response */
                $response = $method === 'get'
                    ? $pending->get($endpoint, $data)
                    : $pending->post($endpoint, $data);
            } catch (ConnectionException $e) {
                if ($attempt >= $attempts) {
                    throw new KommoException("Kommo inacessível em {$endpoint}: {$e->getMessage()}", 0, $e);
                }

                $this->backoff($attempt);

                continue;
            }

            $this->logCall($endpoint, $response, (int) round((microtime(true) - $start) * 1000));

            if ($response->status() === 403) {
                Log::critical('Kommo devolveu 403 — possível bloqueio de IP. Comunicação interrompida.', [
                    'endpoint' => $endpoint,
                ]);

                throw new KommoBlockedException("Kommo devolveu 403 em {$endpoint} — possível bloqueio de IP.");
            }

            if ($response->status() === 429 || $response->serverError()) {
                if ($attempt >= $attempts) {
                    throw new KommoException(
                        "Kommo devolveu {$response->status()} em {$endpoint} após {$attempts} tentativas."
                    );
                }

                $this->backoff($attempt);

                continue;
            }

            if ($response->status() === 204) {
                return [];
            }

            if ($response->failed()) {
                throw new KommoException(
                    "Kommo devolveu {$response->status()} em {$endpoint}: " . mb_substr($response->body(), 0, 500)
                );
            }

            return (array) $response->json();
        }
    }

    /**
     * Segura a chamada até haver vaga na janela de 1 segundo.
     * Com rate <= 0 o throttle é desligado — usado nos testes, onde o
     * relógio congelado impediria o limitador de expirar.
     */
    private function waitForSlot(): void
    {
        $perSecond = (int) config('kommo.rate_per_second');

        if ($perSecond <= 0) {
            return;
        }

        while (RateLimiter::tooManyAttempts(self::THROTTLE_KEY, $perSecond)) {
            usleep(100_000);
        }

        RateLimiter::hit(self::THROTTLE_KEY, 1);
    }

    private function backoff(int $attempt): void
    {
        sleep(2 ** ($attempt - 1));
    }

    private function logCall(string $endpoint, Response $response, int $durationMs): void
    {
        $body = $response->status() === 204 ? [] : (array) $response->json();

        $itens = 0;

        foreach ($body['_embedded'] ?? [] as $group) {
            if (is_array($group)) {
                $itens += count($group);
            }
        }

        KommoApiCall::query()->create([
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'duracao_ms' => $durationMs,
            'itens' => $itens,
            'created_at' => now(),
        ]);
    }

    private function token(): string
    {
        $token = $this->token ?? config('kommo.token');

        if (blank($token)) {
            throw new KommoException('KOMMO_TOKEN não configurado no .env.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return $this->baseUrl ?? config('kommo.base_url');
    }
}
