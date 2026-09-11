<?php

use App\Enums\ModoAutomacao;
use App\Enums\StatusExecucaoAutomacao;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use Database\Seeders\AutomacoesDemoSeeder;
use Illuminate\Support\Facades\Http;

it('seeder das automações roda duas vezes sem duplicar', function () {
    Http::fake();

    $this->seed(AutomacoesDemoSeeder::class);
    $this->seed(AutomacoesDemoSeeder::class);

    $porRegra = AutomationRun::query()
        ->join('automation_rules', 'automation_rules.id', '=', 'automation_runs.automation_rule_id')
        ->selectRaw('automation_rules.codigo, count(*) as total')
        ->groupBy('automation_rules.codigo')
        ->pluck('total', 'codigo');

    expect(AutomationRule::query()->count())->toBe(7)
        ->and(AutomationRule::query()->pluck('codigo')->sort()->values()->all())
        ->toBe(['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7'])
        ->and(AutomationRun::query()->where('demo', true)->count())->toBe(200)
        ->and(AutomationRun::query()->where('status', StatusExecucaoAutomacao::Falhou)->count())->toBe(3)
        ->and(AutomationRun::query()->where('status', StatusExecucaoAutomacao::Ignorado)->count())->toBeGreaterThan(5)
        ->and(AutomationRun::query()->where('created_at', '<', now()->subDays(31))->count())->toBe(0)
        ->and((int) $porRegra['A1'])->toBeGreaterThan((int) $porRegra['A6'])
        ->and(AutomationRule::query()->where('codigo', 'A7')->first()?->pipeline_ids)->toBe([6441483]);

    $modos = AutomationRule::query()->orderBy('ordem')->get()->mapWithKeys(
        fn (AutomationRule $r): array => [$r->codigo => $r->modo],
    );

    expect($modos['A1'])->toBe(ModoAutomacao::Ativo)
        ->and($modos['A2'])->toBe(ModoAutomacao::Ativo)
        ->and($modos['A3'])->toBe(ModoAutomacao::SoRegistrar)
        ->and($modos['A7'])->toBe(ModoAutomacao::SoRegistrar);

    Http::assertNothingSent();
});
