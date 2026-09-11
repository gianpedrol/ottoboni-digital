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

/*
|--------------------------------------------------------------------------
| Helpers do motor de follow-up (Fase 2)
|--------------------------------------------------------------------------
*/

function criarMedicoDuda(): App\Models\Doctor
{
    return App\Models\Doctor::query()->firstOrCreate(
        ['agente' => 'duda'],
        [
            'nome' => 'Dr. Eduardo Ottoboni',
            'kommo_pipeline_id' => config('kommo.pipelines.duda'),
            'ativo' => true,
        ],
    );
}

/**
 * Régua ativa com N passos de texto fixo via tarefa no Kommo (o caminho
 * seguro da Fase 2), gatilho de etapa 51001.
 *
 * @param  array<string, mixed>  $overridesPlan
 * @param  array<string, mixed>  $overridesStep
 */
function criarRegua(int $passos = 3, array $overridesPlan = [], array $overridesStep = []): App\Models\FollowupPlan
{
    $doctor = criarMedicoDuda();

    $plan = App\Models\FollowupPlan::query()->create([
        'doctor_id' => $doctor->id,
        'nome' => 'Régua de teste',
        'ativo' => true,
        'gatilho' => App\Enums\FollowupGatilho::Etapa,
        'gatilho_config' => ['status_id' => 51001],
        ...$overridesPlan,
    ]);

    foreach (range(1, $passos) as $i) {
        App\Models\FollowupStep::query()->create([
            'plan_id' => $plan->id,
            'ordem' => $i,
            'offset_horas' => $i * 24,
            'canal' => 'kommo_task',
            'modo' => 'texto_fixo',
            'texto' => "Passo {$i}: oi {nome}, tudo bem?",
            'ativo' => true,
            ...$overridesStep,
        ]);
    }

    return $plan;
}

/*
|--------------------------------------------------------------------------
| Helpers da área de treinamento das agentes de IA
|--------------------------------------------------------------------------
*/

function criarMedicoLuna(): App\Models\Doctor
{
    return App\Models\Doctor::query()->firstOrCreate(
        ['agente' => 'luna'],
        [
            'nome' => 'Dra. Vanessa Ottoboni',
            'kommo_pipeline_id' => config('kommo.pipelines.luna'),
            'ig_user_id' => '17841400000000000',
            'ativo' => true,
        ],
    );
}

/**
 * Revisão já fechada, para alimentar a janela de acurácia do portão.
 */
function revisaoFechada(
    App\Models\Doctor $doctor,
    App\Enums\IaIntent $intent,
    App\Enums\IaGrauEdicao $grau,
    ?App\Enums\IaApprovalStatus $status = null,
): App\Models\IaApproval {
    return App\Models\IaApproval::query()->create([
        'doctor_id' => $doctor->id,
        'canal' => App\Enums\IaCanal::Comentario,
        'intent' => $intent,
        'motivo_fila' => App\Enums\IaMotivoFila::ModoTreinamento,
        'comentario_texto' => 'pergunta de teste',
        'rascunho_dm' => 'rascunho da agente',
        'final_dm' => 'texto final',
        'status' => $status ?? App\Enums\IaApprovalStatus::Enviado,
        'grau_edicao' => $grau,
        'score' => $grau->score(),
        'similaridade' => $grau === App\Enums\IaGrauEdicao::SemEdicao ? 1 : 0.5,
        'revisado_em' => now()->subMinutes(random_int(1, 500)),
    ]);
}

/**
 * Item pendente na fila, do jeito que o n8n cria.
 *
 * @param  array<string, mixed>  $overrides
 */
function itemPendente(App\Models\Doctor $doctor, array $overrides = []): App\Models\IaApproval
{
    return App\Models\IaApproval::query()->create([
        'doctor_id' => $doctor->id,
        'canal' => App\Enums\IaCanal::Comentario,
        'intent' => App\Enums\IaIntent::Consulta,
        'motivo_fila' => App\Enums\IaMotivoFila::ModoTreinamento,
        'ig_id' => '9988776655',
        'ig_username' => 'paciente_teste',
        'comment_id' => 'c_' . uniqid(),
        'comentario_texto' => 'quanto custa a consulta?',
        'rascunho_comentario' => 'Te chamei no direct! 💛',
        'rascunho_dm' => 'A consulta com a Dra. Vanessa custa R$ 900,00 e dura cerca de 1 hora.',
        'status' => App\Enums\IaApprovalStatus::Pendente,
        'modelo' => 'gpt-4.1',
        'expira_em' => now()->addMinutes(30),
        ...$overrides,
    ]);
}

/**
 * POST assinado nas rotas /api/ia/*.
 *
 * @param  array<string, mixed>  $payload
 */
function postIa(string $rota, array $payload, ?string $assinatura = null)
{
    $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $assinatura ??= 'sha256=' . hash_hmac('sha256', (string) $corpo, (string) config('painel.ia.webhook_secret'));

    return test()->call(
        'POST',
        $rota,
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $assinatura],
        (string) $corpo,
    );
}
