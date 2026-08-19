<?php

use App\Models\FollowupRun;
use App\Services\Followup\AgendadorDeFollowups;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');

    // Terça-feira 14h em São Paulo — dentro da janela de envio.
    $this->travelTo(CarbonImmutable::parse('2026-08-18 14:00:00', 'America/Sao_Paulo'));
});

it('agenda os passos da régua para leads que estão na etapa do gatilho', function () {
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads?*' => Http::response(kommoPage([
            fakeLead(['id' => 1001, 'status_id' => 51001]),
            fakeLead(['id' => 1002, 'status_id' => 51002]), // fora da etapa
            fakeLead(['id' => 1003, 'status_id' => 51001]),
            fakeLead(['id' => 1004, 'status_id' => 142]),   // ganho — nunca entra
        ], 'leads'), 200),
    ]);

    $plan = criarRegua(passos: 2);

    $novos = app(AgendadorDeFollowups::class)->agendarParaPlano($plan);

    expect($novos)->toBe(4) // 2 leads × 2 passos
        ->and(FollowupRun::query()->where('lead_id_kommo', 1001)->count())->toBe(2)
        ->and(FollowupRun::query()->where('lead_id_kommo', 1002)->count())->toBe(0)
        ->and(FollowupRun::query()->where('lead_id_kommo', 1004)->count())->toBe(0);
});

it('reprocessar a régua não agenda duas vezes (idempotência)', function () {
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads?*' => Http::response(kommoPage([
            fakeLead(['id' => 1001, 'status_id' => 51001]),
        ], 'leads'), 200),
    ]);

    $plan = criarRegua(passos: 3);
    $agendador = app(AgendadorDeFollowups::class);

    $primeira = $agendador->agendarParaPlano($plan);
    $segunda = $agendador->agendarParaPlano($plan);

    expect($primeira)->toBe(3)
        ->and($segunda)->toBe(0)
        ->and(FollowupRun::query()->count())->toBe(3);
});

it('calcula agendado_para com o offset de cada passo, ajustado à janela', function () {
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads?*' => Http::response(kommoPage([
            fakeLead(['id' => 1001, 'status_id' => 51001]),
        ], 'leads'), 200),
    ]);

    $plan = criarRegua(passos: 2); // offsets: 24h e 48h

    app(AgendadorDeFollowups::class)->agendarParaPlano($plan);

    $runs = FollowupRun::query()->orderBy('agendado_para')->get();

    // 18/08 14h + 24h = 19/08 14h (quarta, dentro da janela).
    expect($runs[0]->agendado_para->setTimezone('America/Sao_Paulo')->format('d/m H:i'))->toBe('19/08 14:00')
        ->and($runs[1]->agendado_para->setTimezone('America/Sao_Paulo')->format('d/m H:i'))->toBe('20/08 14:00');
});

it('adia para a próxima janela quando o offset cai fora do horário permitido', function () {
    // Sábado 19h30 + 4h = sábado 23h30 (fora) → segunda 09h.
    $this->travelTo(CarbonImmutable::parse('2026-08-22 19:30:00', 'America/Sao_Paulo'));

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads?*' => Http::response(kommoPage([
            fakeLead(['id' => 1001, 'status_id' => 51001]),
        ], 'leads'), 200),
    ]);

    $plan = criarRegua(passos: 1, overridesStep: ['offset_horas' => 4]);

    app(AgendadorDeFollowups::class)->agendarParaPlano($plan);

    $run = FollowupRun::query()->first();

    expect($run->agendado_para->setTimezone('America/Sao_Paulo')->format('d/m H:i'))->toBe('24/08 09:00');
});

it('respeita o limite total de follow-ups por lead', function () {
    config()->set('painel.followup.max_por_lead_total', 2);

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads?*' => Http::response(kommoPage([
            fakeLead(['id' => 1001, 'status_id' => 51001]),
        ], 'leads'), 200),
    ]);

    $plan = criarRegua(passos: 5);

    app(AgendadorDeFollowups::class)->agendarParaPlano($plan);

    expect(FollowupRun::query()->where('lead_id_kommo', 1001)->count())->toBe(2);
});
