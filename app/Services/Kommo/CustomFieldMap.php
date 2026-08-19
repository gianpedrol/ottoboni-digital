<?php

namespace App\Services\Kommo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Resolve os campos personalizados dos leads POR NOME — nunca por ID chutado.
 * O mapa vem de GET /leads/custom_fields e fica 24h em cache.
 */
class CustomFieldMap
{
    private const CACHE_KEY = 'kommo:custom_fields_map';

    /** @var array<string, array{id: int, name: string}>|null */
    private ?array $map = null;

    public function __construct(private readonly KommoClient $client) {}

    /**
     * ID do campo para uma das chaves de config('kommo.custom_fields').
     */
    public function idFor(string $configKey): ?int
    {
        $nomeEsperado = config("kommo.custom_fields.{$configKey}");

        if (blank($nomeEsperado)) {
            return null;
        }

        $map = $this->all();
        $procurado = $this->normalize($nomeEsperado);

        // Primeiro por igualdade exata (normalizada)…
        if (isset($map[$procurado])) {
            return $map[$procurado]['id'];
        }

        // …depois por "contém", para tolerar variações tipo "Origem do lead".
        foreach ($map as $nome => $campo) {
            if (Str::contains($nome, $procurado)) {
                return $campo['id'];
            }
        }

        return null;
    }

    /**
     * Mapa completo: nome normalizado => ['id' => …, 'name' => nome original].
     *
     * @return array<string, array{id: int, name: string}>
     */
    public function all(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        return $this->map = Cache::remember(
            self::CACHE_KEY,
            config('kommo.cache.fields_ttl'),
            fn (): array => $this->fetch(),
        );
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->map = null;
        $this->all();
    }

    /**
     * @return array<string, array{id: int, name: string}>
     */
    private function fetch(): array
    {
        $map = [];

        foreach ($this->client->paginate('/leads/custom_fields', [], 'custom_fields') as $field) {
            $map[$this->normalize($field['name'])] = [
                'id' => (int) $field['id'],
                'name' => (string) $field['name'],
            ];
        }

        return $map;
    }

    private function normalize(string $nome): string
    {
        return Str::of($nome)->squish()->lower()->ascii()->toString();
    }
}
