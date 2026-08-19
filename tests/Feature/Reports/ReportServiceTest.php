<?php

use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');
});

/**
 * Mocka a API com um conjunto de leads para o período atual e outro para o
 * período anterior. A ordem das respostas segue a ordem das chamadas do
 * ReportService (período atual primeiro, anterior depois).
 */
function fakeRelatorio(array $leadsAtual, array $leadsAnterior = []): void
{
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/pipelines*' => Http::response(kommoFixture('pipelines'), 200),
        '*/users*' => Http::response(kommoPage([
            ['id' => 11, 'name' => 'Equipe Ottoboni'],
        ], 'users'), 200),
        '*/leads?*' => Http::sequence()
            ->push(kommoPage($leadsAtual, 'leads'), 200)
            ->push(kommoPage($leadsAnterior, 'leads'), 200),
    ]);
}

function gerarRelatorio(array $pipelineIds = null): array
{
    return app(ReportService::class)->generate(
        pipelineIds: $pipelineIds ?? [(int) config('kommo.pipelines.duda')],
        fromLocal: CarbonImmutable::parse('2026-08-01', 'America/Sao_Paulo'),
        toLocal: CarbonImmutable::parse('2026-08-15', 'America/Sao_Paulo'),
    );
}

function leadDeAgosto(array $overrides = []): array
{
    return fakeLead([
        'created_at' => CarbonImmutable::parse(
            $overrides['dia'] ?? '2026-08-05 10:00:00',
            'America/Sao_Paulo',
        )->utc()->getTimestamp(),
        ...$overrides,
    ]);
}

it('a soma das fatias de origem, temperatura e procedimento bate com o total do volume', function () {
    fakeRelatorio([
        leadDeAgosto(['origem' => 'Tráfego pago', 'temperatura' => 'Quente', 'procedimento' => 'Rinoplastia']),
        leadDeAgosto(['origem' => 'Orgânico-IA', 'temperatura' => 'Morno', 'procedimento' => 'Lipo HD']),
        leadDeAgosto(['origem' => null, 'temperatura' => null, 'procedimento' => null]),
        leadDeAgosto(['origem' => 'Orgânico-IA', 'temperatura' => 'Frio', 'procedimento' => 'Rinoplastia']),
    ]);

    $r = gerarRelatorio();
    $total = $r['volume']['total'];

    expect($total)->toBe(4)
        ->and(array_sum(array_column($r['origem']['fatias'], 'total')))->toBe($total)
        ->and(array_sum(array_column($r['temperatura']['fatias'], 'total')))->toBe($total)
        ->and(array_sum(array_column($r['procedimentos']['ranking'], 'total')))->toBe($total);
});

it('lead sem valor entra como "Não informado", nunca some do total', function () {
    fakeRelatorio([
        leadDeAgosto(['origem' => null]),
        leadDeAgosto(['origem' => 'Tráfego pago']),
    ]);

    $r = gerarRelatorio();

    expect($r['origem']['fatias'])->toHaveKey(ReportService::NAO_INFORMADO)
        ->and($r['origem']['fatias'][ReportService::NAO_INFORMADO]['total'])->toBe(1);
});

it('agrupa o volume por dia no fuso de São Paulo', function () {
    // 23h30 de 04/08 em SP = 02h30 UTC de 05/08 — o erro clássico de fuso.
    fakeRelatorio([
        leadDeAgosto(['dia' => '2026-08-04 23:30:00']),
        leadDeAgosto(['dia' => '2026-08-05 08:00:00']),
    ]);

    $r = gerarRelatorio();

    expect($r['volume']['por_dia']['2026-08-04'])->toBe(1)
        ->and($r['volume']['por_dia']['2026-08-05'])->toBe(1);
});

it('compara com o período anterior de mesmo tamanho e calcula a variação', function () {
    fakeRelatorio(
        leadsAtual: [leadDeAgosto(), leadDeAgosto(), leadDeAgosto()],
        leadsAnterior: [fakeLead(), fakeLead()],
    );

    $r = gerarRelatorio();

    expect($r['volume']['total'])->toBe(3)
        ->and($r['volume']['total_periodo_anterior'])->toBe(2)
        ->and($r['volume']['variacao_pct'])->toBe(50.0);
});

it('monta o funil com taxa de ganho e etapas do pipeline do médico', function () {
    fakeRelatorio([
        leadDeAgosto(['status_id' => 51001]),
        leadDeAgosto(['status_id' => 51002]),
        leadDeAgosto(['status_id' => 51003]),
        leadDeAgosto(['status_id' => 142]),
        leadDeAgosto(['status_id' => 143]),
    ]);

    $r = gerarRelatorio();
    $funil = $r['funil']['funis'][0];

    expect($funil['total'])->toBe(5)
        ->and($funil['ganhos'])->toBe(1)
        ->and($funil['perdidos'])->toBe(1)
        ->and($funil['taxa_ganho_pct'])->toBe(20.0);

    $etapas = collect($funil['etapas'])->keyBy('status_id');

    // "Chegou à etapa" = está nela, adiante, ou ganhou; perdido só na 1ª.
    expect($etapas[51001]['chegaram'])->toBe(5)
        ->and($etapas[51002]['chegaram'])->toBe(3)
        ->and($etapas[51003]['chegaram'])->toBe(2);
});

it('ranking de procedimentos limita ao top 10 e agrupa o resto em Outros', function () {
    $leads = [];

    foreach (range(1, 12) as $i) {
        $leads[] = leadDeAgosto(['procedimento' => "Procedimento {$i}"]);
        $leads[] = leadDeAgosto(['procedimento' => "Procedimento {$i}"]);
    }

    // Um extra no procedimento 1 para garantir ordenação estável no topo.
    $leads[] = leadDeAgosto(['procedimento' => 'Procedimento 1']);

    fakeRelatorio($leads);

    $r = gerarRelatorio();
    $ranking = $r['procedimentos']['ranking'];

    expect(count($ranking))->toBe(11)
        ->and($ranking)->toHaveKey('Outros')
        ->and(array_sum(array_column($ranking, 'total')))->toBe($r['volume']['total']);
});

it('produtividade agrupa por responsável usando o nome do usuário do Kommo', function () {
    fakeRelatorio([
        leadDeAgosto(['responsible_user_id' => 11]),
        leadDeAgosto(['responsible_user_id' => 11]),
    ]);

    $r = gerarRelatorio();

    expect($r['produtividade']['por_responsavel'])->toHaveKey('Equipe Ottoboni')
        ->and($r['produtividade']['por_responsavel']['Equipe Ottoboni'])->toBe(2)
        ->and($r['produtividade']['tempo_medio_primeira_nota_horas'])->toBeNull();
});

it('bloco de follow-up vem zerado (e não quebrado) enquanto a Fase 2 não existe', function () {
    fakeRelatorio([leadDeAgosto()]);

    $r = gerarRelatorio();

    expect($r['followup']['enviados'])->toBe(0)
        ->and($r['followup']['taxa_resposta_pct'])->toBeNull()
        ->and($r['followup']['reativados'])->toBeNull();
});
