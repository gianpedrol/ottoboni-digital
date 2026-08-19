<?php

use App\Enums\FollowupCanal;
use App\Enums\FollowupRunStatus;
use App\Enums\OrigemTexto;
use App\Models\FollowupMessage;
use App\Models\FollowupRun;
use App\Models\WebhookLog;

beforeEach(function () {
    config()->set('painel.n8n.webhook_secret', 'segredo-teste');
});

/**
 * Run no estado pós-disparo via n8n: enviado, aguardando o canal final.
 */
function runAguardandoCallback(): FollowupRun
{
    $plan = criarRegua(passos: 2, overridesStep: ['canal' => 'auto']);

    $run = FollowupRun::query()->create([
        'plan_id' => $plan->id,
        'step_id' => $plan->steps->first()->id,
        'lead_id_kommo' => 1001,
        'doctor_id' => $plan->doctor_id,
        'status' => FollowupRunStatus::Enviado,
        'agendado_para' => now()->subHour(),
        'executado_em' => now()->subMinutes(2),
    ]);

    FollowupMessage::query()->create([
        'run_id' => $run->id,
        'canal' => FollowupCanal::Auto,
        'texto_final' => 'Oi! Passando pra saber se ficou alguma dúvida',
        'origem_texto' => OrigemTexto::Fixo,
    ]);

    return $run;
}

/**
 * @param  array<string, mixed>  $payload
 */
function postCallback(array $payload, ?string $assinatura = null)
{
    $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $assinatura ??= 'sha256=' . hash_hmac('sha256', $corpo, 'segredo-teste');

    return test()->call(
        'POST',
        '/api/followup/callback',
        server: [
            'HTTP_X-Signature' => $assinatura,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: $corpo,
    );
}

it('rejeita callback sem assinatura válida com 401 e registra no log', function () {
    $run = runAguardandoCallback();

    postCallback(['run_id' => $run->id, 'status' => 'enviado'], assinatura: 'sha256=forjada')
        ->assertUnauthorized();

    expect($run->refresh()->canal_usado)->toBeNull()
        ->and(WebhookLog::query()->where('direcao', 'in')->where('assinatura_ok', false)->count())->toBe(1);
});

it('passo fora da janela de 24h do Instagram cai para o Kommo e canal_usado registra isso', function () {
    // O n8n resolveu canal "auto": janela fechada → mandou via tarefa Kommo.
    $run = runAguardandoCallback();

    postCallback([
        'run_id' => $run->id,
        'status' => 'enviado',
        'canal_usado' => 'kommo_task',
        'texto_final' => 'Oi! Passando pra saber se ficou alguma dúvida',
        'origem_texto' => 'fixo',
    ])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(FollowupRunStatus::Enviado)
        ->and($run->canal_usado)->toBe(FollowupCanal::KommoTask);

    $mensagem = $run->messages()->first();

    expect($mensagem->canal)->toBe(FollowupCanal::KommoTask)
        ->and($mensagem->resposta_n8n['canal_usado'])->toBe('kommo_task');
});

it('reenviar o mesmo run_id duas vezes gera UM efeito (idempotência por run_id)', function () {
    $run = runAguardandoCallback();

    $payload = [
        'run_id' => $run->id,
        'status' => 'enviado',
        'canal_usado' => 'instagram',
        'texto_final' => 'Texto definitivo',
        'origem_texto' => 'ia_ancorada',
    ];

    postCallback($payload)->assertOk();

    // Segundo callback tenta trocar o canal — não pode ter efeito.
    postCallback([...$payload, 'canal_usado' => 'kommo_task'])
        ->assertOk()
        ->assertJson(['ignorado' => 'run já resolvido']);

    expect($run->refresh()->canal_usado)->toBe(FollowupCanal::Instagram)
        ->and(WebhookLog::query()->where('direcao', 'in')->count())->toBe(2);
});

it('status falhou marca o run como falhou com o erro registrado', function () {
    $run = runAguardandoCallback();

    postCallback([
        'run_id' => $run->id,
        'status' => 'falhou',
        'erro' => 'Instagram devolveu erro 190 (token)',
    ])->assertOk();

    expect($run->refresh()->status)->toBe(FollowupRunStatus::Falhou)
        ->and($run->messages()->first()->erro)->toContain('190');
});

it('status fora_da_janela registra o motivo específico', function () {
    $run = runAguardandoCallback();

    postCallback(['run_id' => $run->id, 'status' => 'fora_da_janela'])->assertOk();

    expect($run->refresh()->status)->toBe(FollowupRunStatus::Falhou)
        ->and($run->motivo_cancelamento)->toBe('fora da janela de 24h do Instagram');
});

it('run desconhecido devolve 404 sem quebrar', function () {
    runAguardandoCallback();

    postCallback(['run_id' => 999999, 'status' => 'enviado'])->assertNotFound();
});
