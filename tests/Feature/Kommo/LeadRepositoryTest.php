<?php

use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');
});

function filtrosPadrao(array $overrides = []): LeadFilters
{
    return new LeadFilters(
        pipelineIds: $overrides['pipelineIds'] ?? [(int) config('kommo.pipelines.duda')],
        from: $overrides['from'] ?? CarbonImmutable::now()->subDays(30),
        to: $overrides['to'] ?? CarbonImmutable::now(),
        search: $overrides['search'] ?? null,
        origem: $overrides['origem'] ?? null,
        temperatura: $overrides['temperatura'] ?? null,
        procedimento: $overrides['procedimento'] ?? null,
        statusId: $overrides['statusId'] ?? null,
        geradoPelaAgente: $overrides['geradoPelaAgente'] ?? null,
    );
}

function fakeKommoLeads(array $leads): void
{
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads?*' => Http::response(kommoPage($leads, 'leads'), 200),
    ]);
}

it('aplica filtros de campo personalizado em memória, sem nova chamada à API', function () {
    fakeKommoLeads([
        fakeLead(['temperatura' => 'Quente']),
        fakeLead(['temperatura' => 'Morno']),
        fakeLead(['temperatura' => 'Quente']),
    ]);

    $repo = app(LeadRepository::class);

    $todos = $repo->leads(filtrosPadrao());
    $quentes = $repo->leads(filtrosPadrao(['temperatura' => 'Quente']));

    expect($todos)->toHaveCount(3)
        ->and($quentes)->toHaveCount(2);

    // 1 chamada para custom_fields + 1 para leads: trocar o filtro de
    // temperatura não pode disparar chamada nova.
    Http::assertSentCount(2);
});

it('filtra por origem, procedimento, etapa e gerado pela agente', function () {
    fakeKommoLeads([
        fakeLead(['origem' => 'Tráfego pago', 'procedimento' => 'Rinoplastia', 'status_id' => 51001]),
        fakeLead(['origem' => 'Orgânico-IA', 'procedimento' => 'Lipo HD', 'status_id' => 51002]),
        fakeLead(['origem' => 'Orgânico-IA', 'procedimento' => 'Lipo HD', 'status_id' => 51002, 'name' => 'Cadastro Manual']),
    ]);

    $repo = app(LeadRepository::class);

    expect($repo->leads(filtrosPadrao(['origem' => 'Tráfego pago'])))->toHaveCount(1)
        ->and($repo->leads(filtrosPadrao(['procedimento' => 'Lipo HD'])))->toHaveCount(2)
        ->and($repo->leads(filtrosPadrao(['statusId' => 51002])))->toHaveCount(2)
        ->and($repo->leads(filtrosPadrao(['geradoPelaAgente' => true])))->toHaveCount(2)
        ->and($repo->leads(filtrosPadrao(['geradoPelaAgente' => false])))->toHaveCount(1);
});

it('pagina em memória mantendo o total correto', function () {
    fakeKommoLeads(array_map(fn () => fakeLead(), range(1, 25)));

    $paginado = app(LeadRepository::class)->paginateLeads(filtrosPadrao(), page: 2, perPage: 10);

    expect($paginado->total())->toBe(25)
        ->and($paginado->items())->toHaveCount(10)
        ->and($paginado->currentPage())->toBe(2);
});

it('manda o período como timestamp Unix e o pipeline no filtro da API', function () {
    $from = CarbonImmutable::parse('2026-08-01 00:00:00', 'America/Sao_Paulo')->utc();
    $to = CarbonImmutable::parse('2026-08-31 23:59:59', 'America/Sao_Paulo')->utc();

    fakeKommoLeads([]);

    app(LeadRepository::class)->leads(filtrosPadrao(['from' => $from, 'to' => $to]));

    Http::assertSent(function ($request) use ($from, $to) {
        if (! str_contains($request->url(), '/leads?')) {
            return true;
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return (int) $query['filter']['created_at']['from'] === $from->getTimestamp()
            && (int) $query['filter']['created_at']['to'] === $to->getTimestamp()
            && (int) $query['filter']['pipeline_id'][0] === (int) config('kommo.pipelines.duda');
    });
});

it('invalida o cache no "Atualizar agora" e busca de novo', function () {
    fakeKommoLeads([fakeLead()]);

    $repo = app(LeadRepository::class);
    $filtros = filtrosPadrao();

    $repo->leads($filtros);
    $repo->invalidate($filtros);
    $repo->leads($filtros);

    // custom_fields (1) + leads (2)
    Http::assertSentCount(3);
});

it('registra a hora da busca para exibir "dados de HH:MM"', function () {
    fakeKommoLeads([fakeLead()]);

    $repo = app(LeadRepository::class);
    $filtros = filtrosPadrao();

    expect($repo->fetchedAt($filtros))->toBeNull();

    $repo->leads($filtros);

    expect($repo->fetchedAt($filtros))->not->toBeNull();
});

it('separa o cache por pipeline: recepção de um médico não reaproveita dados do outro', function () {
    fakeKommoLeads([fakeLead()]);

    $repo = app(LeadRepository::class);

    $duda = filtrosPadrao(['pipelineIds' => [(int) config('kommo.pipelines.duda')]]);
    $luna = filtrosPadrao(['pipelineIds' => [(int) config('kommo.pipelines.luna')]]);

    expect($duda->baseKey())->not->toBe($luna->baseKey());
});
