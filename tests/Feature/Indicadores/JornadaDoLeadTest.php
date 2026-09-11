<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Filament\Widgets\ClinicaResumoWidget;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Indicadores\JornadaDoLead;
use App\Services\Kommo\DTO\LeadData;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

// IDs reais do pipeline do Dr Eduardo (config/etapas.php)
const EDU_FUP_1 = 84709188;
const EDU_AGENDOU = 54917479;
const EDU_REALIZOU = 56708683;
const EDU_NEGOCIACAO = 54917483;
const EDU_EM_ATENDIMENTO = 54917475;

/**
 * @param  array<string, mixed>  $extra
 */
function leadNaEtapa(int $statusId, array $extra = []): LeadData
{
    static $id = 0;

    return new LeadData(
        id: ++$id,
        name: "Paciente {$id}",
        pipelineId: (int) config('kommo.pipelines.duda'),
        statusId: $statusId,
        responsibleUserId: null,
        createdAt: CarbonImmutable::now()->subDays(3),
        updatedAt: null,
        closedAt: null,
        price: $extra['price'] ?? 0,
        origem: null,
        temperatura: null,
        procedimento: null,
        urgencia: null,
        score: null,
        instagram: null,
        contactIds: [],
        lossReason: null,
        proximaConsulta: $extra['proximaConsulta'] ?? null,
        comparecimento: $extra['comparecimento'] ?? null,
        valorProposta: $extra['valorProposta'] ?? null,
    );
}

it('conta quem chegou em cada etapa do funil, incluindo quem já passou dela', function () {
    $leads = collect([
        leadNaEtapa(EDU_EM_ATENDIMENTO),
        leadNaEtapa(EDU_FUP_1),
        leadNaEtapa(EDU_AGENDOU),
        leadNaEtapa(EDU_REALIZOU),
        leadNaEtapa(EDU_NEGOCIACAO, ['valorProposta' => 18000]),
        leadNaEtapa(142, ['price' => 25000]),
    ]);

    $funil = app(JornadaDoLead::class)->funil($leads);

    expect(array_values($funil))->toBe([6, 6, 4, 3, 2, 1]);
});

it('lead perdido que tinha consulta marcada e compareceu conta como consulta realizada', function () {
    $perdido = leadNaEtapa(143, [
        'proximaConsulta' => CarbonImmutable::now()->subDay(),
        'comparecimento' => 'Compareceu',
    ]);

    expect(app(JornadaDoLead::class)->posicao($perdido))->toBe(3);
});

it('resume taxas, valores e comparecimento', function () {
    $leads = collect([
        leadNaEtapa(EDU_FUP_1),
        leadNaEtapa(EDU_AGENDOU, ['comparecimento' => 'Faltou']),
        leadNaEtapa(EDU_REALIZOU, ['comparecimento' => 'Compareceu']),
        leadNaEtapa(EDU_NEGOCIACAO, ['valorProposta' => 18000, 'comparecimento' => 'Compareceu']),
        leadNaEtapa(142, ['price' => 20000, 'comparecimento' => 'Compareceu']),
    ]);

    $r = app(JornadaDoLead::class)->resumo($leads);

    expect($r['total'])->toBe(5)
        ->and($r['agendaram'])->toBe(4)
        ->and($r['taxa_agendamento_pct'])->toBe(80.0)
        ->and($r['em_negociacao'])->toBe(1)
        ->and($r['orcamentos_abertos_valor'])->toBe(18000.0)
        ->and($r['contratados'])->toBe(1)
        ->and($r['ticket_medio'])->toBe(20000.0)
        ->and($r['em_fup'])->toBe(1)
        ->and($r['compareceram'])->toBe(3)
        ->and($r['faltaram'])->toBe(1)
        ->and($r['comparecimento_pct'])->toBe(75.0);
});

it('sem leads as taxas ficam nulas, nunca divisão por zero', function () {
    $r = app(JornadaDoLead::class)->resumo(collect());

    expect($r['taxa_agendamento_pct'])->toBeNull()
        ->and($r['comparecimento_pct'])->toBeNull()
        ->and($r['ticket_medio'])->toBeNull();
});

it('resumo da clínica soma as parcelas em atraso', function () {
    $duda = Doctor::query()->create(['nome' => 'Dr. Eduardo', 'agente' => 'duda', 'kommo_pipeline_id' => config('kommo.pipelines.duda'), 'ativo' => true]);
    Doctor::query()->create(['nome' => 'Dra. Vanessa', 'agente' => 'luna', 'kommo_pipeline_id' => config('kommo.pipelines.luna'), 'ativo' => true]);

    $paciente = Patient::query()->create(['nome' => 'Ana', 'telefone' => '+5541999990000', 'doctor_id' => $duda->id]);
    $recebivel = Receivable::query()->create([
        'patient_id' => $paciente->id, 'doctor_id' => $duda->id, 'descricao' => 'Mamoplastia',
        'valor_total' => 3000, 'forma_pagamento' => 'pix',
    ]);
    $recebivel->installments()->create(['numero' => 1, 'valor' => 1500, 'vencimento' => now()->subDays(10), 'status' => 'aberto']);

    $regra = AutomationRule::query()->create(['nome' => 'A1', 'gatilho' => [], 'acoes' => [], 'pipeline_ids' => []]);
    AutomationRun::query()->create(['automation_rule_id' => $regra->id, 'kommo_lead_id' => 1, 'status' => 'falhou']);

    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@teste.local', 'password' => 'x',
        'role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos,
    ]);

    $this->actingAs($admin);

    Livewire::test(ClinicaResumoWidget::class)
        ->assertOk()
        ->assertSee('1.500,00')
        ->assertSee('1 parcelas vencidas');
});

it('recepção não vê o resumo financeiro', function () {
    $recepcao = User::query()->create([
        'name' => 'Recepção', 'email' => 'recepcao@teste.local', 'password' => 'x',
        'role' => UserRole::Recepcao, 'doctor_scope' => DoctorScope::Eduardo,
    ]);

    $this->actingAs($recepcao);

    expect(ClinicaResumoWidget::canView())->toBeFalse();
});
