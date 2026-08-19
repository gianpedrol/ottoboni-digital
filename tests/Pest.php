<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers de fixture do Kommo
|--------------------------------------------------------------------------
| A API do Kommo é SEMPRE mockada nos testes — nada bate em produção.
*/

/**
 * Lead cru no formato da API, com os campos das agentes preenchidos.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeLead(array $overrides = []): array
{
    static $sequence = 0;
    $sequence++;

    $defaults = [
        'origem' => 'Orgânico-IA',
        'temperatura' => 'Morno',
        'procedimento' => 'Rinoplastia',
        'urgencia' => 'Sem pressa',
        'score' => 70,
        'instagram' => "paciente{$sequence}",
    ];

    // array_key_exists para permitir "campo vazio de propósito" com null.
    $cf = [];

    foreach ($defaults as $key => $default) {
        $cf[$key] = array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    }

    $fieldIds = [
        'origem' => 101,
        'temperatura' => 102,
        'procedimento' => 103,
        'urgencia' => 104,
        'score' => 105,
        'instagram' => 106,
    ];

    $customFieldsValues = [];

    foreach ($fieldIds as $key => $id) {
        if ($cf[$key] === null) {
            continue;
        }

        $customFieldsValues[] = [
            'field_id' => $id,
            'field_name' => ucfirst($key),
            'values' => [['value' => $cf[$key]]],
        ];
    }

    return [
        'id' => $overrides['id'] ?? (900000 + $sequence),
        'name' => $overrides['name'] ?? "IG @paciente{$sequence}",
        'price' => $overrides['price'] ?? 0,
        'pipeline_id' => $overrides['pipeline_id'] ?? (int) config('kommo.pipelines.duda'),
        'status_id' => $overrides['status_id'] ?? 51001,
        'responsible_user_id' => $overrides['responsible_user_id'] ?? 11,
        'created_at' => $overrides['created_at'] ?? now()->subDay()->getTimestamp(),
        'updated_at' => $overrides['updated_at'] ?? now()->subHours(2)->getTimestamp(),
        'closed_at' => $overrides['closed_at'] ?? null,
        'custom_fields_values' => $customFieldsValues,
        '_embedded' => [
            'contacts' => $overrides['contacts'] ?? [['id' => 500 + $sequence, 'is_main' => true]],
        ],
    ];
}

/**
 * Corpo de uma página de listagem da API do Kommo.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function kommoPage(array $items, string $embeddedKey, bool $hasNext = false): array
{
    $body = [
        '_page' => 1,
        '_links' => ['self' => ['href' => 'https://ottoboni.kommo.com/api/v4']],
        '_embedded' => [$embeddedKey => $items],
    ];

    if ($hasNext) {
        $body['_links']['next'] = ['href' => 'https://ottoboni.kommo.com/api/v4?page=2'];
    }

    return $body;
}

/**
 * @return array<string, mixed>
 */
function kommoFixture(string $name): array
{
    $json = file_get_contents(base_path("tests/Fixtures/kommo/{$name}.json"));

    return json_decode($json, associative: true);
}
