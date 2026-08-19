<?php

use App\Enums\FollowupCanal;
use App\Enums\FollowupRunStatus;
use App\Models\FollowupMessage;
use App\Models\FollowupRun;
use App\Services\Followup\ExecutorDeFollowup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');

    // Terça-feira 14h em São Paulo — dentro da janela de envio.
    $this->travelTo(CarbonImmutable::parse('2026-08-18 14:00:00', 'America/Sao_Paulo'));
});

/**
 * Run agendado e vencido para o lead 1001, com respostas padrão do Kommo.
 *
 * @param  array<string, mixed>  $lead  overrides do lead no Kommo
 */
function runPronto(array $lead = [], array $overridesStep = [], int $passos = 3): FollowupRun
{
    $plan = criarRegua(passos: $passos, overridesStep: $overridesStep);

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/1001*' => Http::response(fakeLead([
            'id' => 1001,
            'name' => 'IG @fulana',
            'instagram' => 'fulana',
            'status_id' => 51001,
            ...$lead,
        ]), 200),
        '*/tasks*' => Http::sequence()
            ->push(kommoPage([], 'tasks'), 200) // consulta de tarefas abertas
            ->push(['_embedded' => ['tasks' => [['id' => 777]]]], 200) // criação
            ->push(kommoPage([], 'tasks'), 200)
            ->push(['_embedded' => ['tasks' => [['id' => 778]]]], 200),
        '*/leads/pipelines*' => Http::response(kommoFixture('pipelines'), 200),
        'n8n.local/*' => Http::response(['ok' => true], 202),
    ]);

    $runs = [];

    foreach ($plan->steps as $i => $step) {
        $runs[] = FollowupRun::query()->create([
            'plan_id' => $plan->id,
            'step_id' => $step->id,
            'lead_id_kommo' => 1001,
            'doctor_id' => $plan->doctor_id,
            'status' => FollowupRunStatus::Agendado,
            'agendado_para' => now()->subMinutes(5)->addHours($i * 24),
        ]);
    }

    return $runs[0];
}

it('texto fixo + tarefa Kommo: cria a tarefa, marca enviado e grava a mensagem', function () {
    $run = runPronto();

    app(ExecutorDeFollowup::class)->executar($run);

    $run->refresh();

    expect($run->status)->toBe(FollowupRunStatus::Enviado)
        ->and($run->canal_usado)->toBe(FollowupCanal::KommoTask)
        ->and($run->executado_em)->not->toBeNull();

    $mensagem = FollowupMessage::query()->where('run_id', $run->id)->first();

    // Placeholder {nome} resolvido com o @ do lead da agente.
    expect($mensagem->texto_final)->toBe('Passo 1: oi @fulana, tudo bem?');

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/tasks') || $request->method() !== 'POST') {
            return true;
        }

        $payload = $request->data()[0];

        return $payload['entity_id'] === 1001
            && str_contains($payload['text'], 'oi @fulana');
    });
});

it('executar o mesmo run duas vezes gera UM envio (idempotência)', function () {
    $run = runPronto(passos: 1);

    $executor = app(ExecutorDeFollowup::class);
    $executor->executar($run);
    $executor->executar($run->refresh());

    expect(FollowupMessage::query()->where('run_id', $run->id)->count())->toBe(1);

    // Só um POST de criação de tarefa no meio das chamadas.
    $criacoes = 0;

    Http::recorded(function ($request) use (&$criacoes): void {
        if (str_contains($request->url(), '/tasks') && $request->method() === 'POST') {
            $criacoes++;
        }
    });

    expect($criacoes)->toBe(1);
});

it('lead que ganhou tem este e os passos seguintes cancelados', function () {
    $run = runPronto(lead: ['status_id' => 142]);

    app(ExecutorDeFollowup::class)->executar($run);

    expect(FollowupRun::query()->where('status', FollowupRunStatus::Cancelado)->count())->toBe(3)
        ->and($run->refresh()->motivo_cancelamento)->toBe('lead ganhou');
});

it('lead que saiu da etapa do gatilho tem os passos cancelados', function () {
    $run = runPronto(lead: ['status_id' => 51003]);

    app(ExecutorDeFollowup::class)->executar($run);

    expect($run->refresh()->status)->toBe(FollowupRunStatus::Cancelado)
        ->and($run->motivo_cancelamento)->toBe('lead saiu da etapa do gatilho');
});

it('tarefa humana aberta cancela o follow-up (humano assumiu)', function () {
    $plan = criarRegua(passos: 1);

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/1001*' => Http::response(fakeLead(['id' => 1001, 'status_id' => 51001]), 200),
        '*/tasks*' => Http::response(kommoPage([
            ['id' => 5, 'text' => 'Ligar para a paciente', 'is_completed' => false, 'complete_till' => now()->addDay()->getTimestamp()],
        ], 'tasks'), 200),
    ]);

    $run = FollowupRun::query()->create([
        'plan_id' => $plan->id,
        'step_id' => $plan->steps->first()->id,
        'lead_id_kommo' => 1001,
        'doctor_id' => $plan->doctor_id,
        'status' => FollowupRunStatus::Agendado,
        'agendado_para' => now()->subMinutes(5),
    ]);

    app(ExecutorDeFollowup::class)->executar($run);

    expect($run->refresh()->status)->toBe(FollowupRunStatus::Cancelado)
        ->and($run->motivo_cancelamento)->toBe('tarefa humana aberta no lead');
});

it('fora da janela de horário adia em vez de falhar', function () {
    $run = runPronto(passos: 1);

    // Domingo de madrugada — fora da janela.
    $this->travelTo(CarbonImmutable::parse('2026-08-23 02:00:00', 'America/Sao_Paulo'));

    app(ExecutorDeFollowup::class)->executar($run);

    $run->refresh();

    expect($run->status)->toBe(FollowupRunStatus::Agendado)
        ->and($run->agendado_para->setTimezone('America/Sao_Paulo')->format('d/m H:i'))->toBe('24/08 09:00');
});

it('máximo de 1 follow-up por lead a cada 24h: o segundo é adiado', function () {
    $run = runPronto(passos: 2);

    $executor = app(ExecutorDeFollowup::class);
    $executor->executar($run);

    // Segundo passo já vencido (forçando), mas o lead recebeu há pouco.
    $segundo = FollowupRun::query()
        ->where('status', FollowupRunStatus::Agendado)
        ->first();

    $segundo->update(['agendado_para' => now()->subMinute()]);

    $executor->executar($segundo->refresh());

    $segundo->refresh();

    expect($segundo->status)->toBe(FollowupRunStatus::Agendado)
        ->and($segundo->agendado_para->gt(now()->addHours(23)))->toBeTrue();
});

it('canal auto vai para o n8n com assinatura HMAC e payload do contrato', function () {
    config()->set('painel.n8n.followup_url', 'https://n8n.local/webhook/followup');
    config()->set('painel.n8n.webhook_secret', 'segredo-teste');

    $run = runPronto(passos: 1, overridesStep: ['canal' => 'auto']);

    app(ExecutorDeFollowup::class)->executar($run);

    expect($run->refresh()->status)->toBe(FollowupRunStatus::Enviado)
        ->and($run->canal_usado)->toBeNull(); // canal final chega no callback

    Http::assertSent(function ($request) use ($run): bool {
        if (! str_contains($request->url(), 'n8n.local')) {
            return true;
        }

        $esperada = 'sha256=' . hash_hmac('sha256', $request->body(), 'segredo-teste');
        $payload = json_decode($request->body(), true);

        return $request->header('X-Signature')[0] === $esperada
            && $payload['conta'] === 'duda'
            && $payload['lead_id'] === 1001
            && $payload['canal'] === 'auto'
            && $payload['run_id'] === $run->id
            && str_contains($payload['callback_url'], '/api/followup/callback');
    });
});

it('canal auto sem n8n configurado falha com erro claro, sem travar a fila', function () {
    config()->set('painel.n8n.followup_url', null);

    $run = runPronto(passos: 1, overridesStep: ['canal' => 'auto']);

    app(ExecutorDeFollowup::class)->executar($run);

    $run->refresh();
    $mensagem = FollowupMessage::query()->where('run_id', $run->id)->first();

    expect($run->status)->toBe(FollowupRunStatus::Falhou)
        ->and($mensagem->erro)->toContain('n8n não configurado');
});
