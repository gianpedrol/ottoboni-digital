<?php

use App\Enums\FollowupRunStatus;
use App\Models\FollowupRun;
use App\Services\Followup\CanceladorDeSeguintes;
use App\Services\Followup\SimuladorDeRegua;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');

    // Terça-feira 10h em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-08-18 10:00:00', 'America/Sao_Paulo'));
});

it('régua com 5 passos simula as datas corretamente e NÃO envia nada', function () {
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/1001*' => Http::response(fakeLead([
            'id' => 1001,
            'name' => 'IG @fulana',
            'instagram' => 'fulana',
            'procedimento' => 'Rinoplastia',
        ]), 200),
    ]);

    $plan = criarRegua(passos: 5); // offsets 24h, 48h, 72h, 96h, 120h

    $simulacao = app(SimuladorDeRegua::class)->simular($plan, 1001);

    expect($simulacao['passos'])->toHaveCount(5)
        ->and($simulacao['passos'][0]['previsto_para'])->toStartWith('19/08/2026 10:00')
        ->and($simulacao['passos'][1]['previsto_para'])->toStartWith('20/08/2026 10:00')
        ->and($simulacao['passos'][2]['previsto_para'])->toStartWith('21/08/2026 10:00')
        ->and($simulacao['passos'][3]['previsto_para'])->toStartWith('22/08/2026 10:00')
        // 23/08 é domingo (fora da janela) → passo 5 vai para segunda 09h.
        ->and($simulacao['passos'][4]['previsto_para'])->toStartWith('24/08/2026 09:00')
        ->and($simulacao['passos'][0]['texto'])->toBe('Passo 1: oi @fulana, tudo bem?');

    // Nenhum run agendado, nenhuma tarefa criada, nenhum webhook.
    expect(FollowupRun::query()->count())->toBe(0);

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && ! str_contains($request->url(), 'custom_fields'));
});

it('simulador avisa quando o passo de Instagram não tem @ para usar', function () {
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/1001*' => Http::response(fakeLead([
            'id' => 1001,
            'name' => 'Maria da Silva',
            'instagram' => null,
        ]), 200),
    ]);

    $plan = criarRegua(passos: 1, overridesStep: ['canal' => 'auto']);

    $simulacao = app(SimuladorDeRegua::class)->simular($plan, 1001);

    expect($simulacao['passos'][0]['observacao'])->toContain('sem @ do Instagram');
});

it('lead que responde tem os passos seguintes cancelados', function () {
    $plan = criarRegua(passos: 4);

    $runs = [];

    foreach ($plan->steps as $i => $step) {
        $runs[] = FollowupRun::query()->create([
            'plan_id' => $plan->id,
            'step_id' => $step->id,
            'lead_id_kommo' => 1001,
            'doctor_id' => $plan->doctor_id,
            'status' => $i === 0 ? FollowupRunStatus::Enviado : FollowupRunStatus::Agendado,
            'agendado_para' => now()->addHours($i * 24),
            'executado_em' => $i === 0 ? now() : null,
        ]);
    }

    // O lead respondeu ao primeiro passo.
    app(CanceladorDeSeguintes::class)->marcarRespondido($runs[0]);

    expect($runs[0]->refresh()->status)->toBe(FollowupRunStatus::Respondido)
        ->and($runs[1]->refresh()->status)->toBe(FollowupRunStatus::Cancelado)
        ->and($runs[1]->motivo_cancelamento)->toBe('lead respondeu')
        ->and($runs[2]->refresh()->status)->toBe(FollowupRunStatus::Cancelado)
        ->and($runs[3]->refresh()->status)->toBe(FollowupRunStatus::Cancelado);
});

it('cancelar seguintes não mexe no que já foi enviado', function () {
    $plan = criarRegua(passos: 3);

    $enviado = FollowupRun::query()->create([
        'plan_id' => $plan->id,
        'step_id' => $plan->steps[0]->id,
        'lead_id_kommo' => 1001,
        'doctor_id' => $plan->doctor_id,
        'status' => FollowupRunStatus::Enviado,
        'agendado_para' => now()->subDay(),
        'executado_em' => now()->subDay(),
    ]);

    $agendado = FollowupRun::query()->create([
        'plan_id' => $plan->id,
        'step_id' => $plan->steps[1]->id,
        'lead_id_kommo' => 1001,
        'doctor_id' => $plan->doctor_id,
        'status' => FollowupRunStatus::Agendado,
        'agendado_para' => now()->addDay(),
    ]);

    app(CanceladorDeSeguintes::class)->cancelar($agendado, 'teste');

    expect($enviado->refresh()->status)->toBe(FollowupRunStatus::Enviado)
        ->and($agendado->refresh()->status)->toBe(FollowupRunStatus::Cancelado);
});
