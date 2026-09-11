<?php

use App\Enums\DoctorScope;
use App\Enums\ModoAutomacao;
use App\Enums\StatusExecucaoAutomacao;
use App\Enums\UserRole;
use App\Filament\Pages\SimuladorAutomacao;
use App\Filament\Resources\AutomationRules\Pages\ListAutomationRules;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\User;
use Database\Seeders\AutomacoesDemoSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/pipelines*' => Http::response(kommoFixture('pipelines'), 200),
    ]);
});

// Leitura é permitida; escrita no Kommo, nunca.
afterEach(function () {
    Http::assertNotSent(fn ($request): bool => $request->method() !== 'GET');
});

function usuarioDasAutomacoes(UserRole $role = UserRole::Admin): User
{
    return User::query()->create([
        'name' => 'Teste',
        'email' => uniqid() . '@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => $role,
        'doctor_scope' => DoctorScope::Ambos,
    ]);
}

it('admin abre regras, formulários, execuções, simulador e mapa', function () {
    $this->seed(AutomacoesDemoSeeder::class);

    $regra = AutomationRule::query()->where('codigo', 'A6')->firstOrFail();
    $run = AutomationRun::query()->where('status', StatusExecucaoAutomacao::Executado)->firstOrFail();

    $this->actingAs(usuarioDasAutomacoes());

    $this->get('/painel/automacoes/regras')
        ->assertOk()
        ->assertSee('Contrato assinado')
        ->assertSee('Demonstração');
    $this->get('/painel/automacoes/regras/create')->assertOk();
    $this->get("/painel/automacoes/regras/{$regra->id}/edit")->assertOk()->assertSee('Fluxo cirurgias');
    $this->get('/painel/automacoes/execucoes')->assertOk();
    $this->get("/painel/automacoes/execucoes/{$run->id}")->assertOk()->assertSee('/api/v4/leads');
    $this->get('/painel/automacoes/simulador')->assertOk();
    $this->get('/painel/automacoes/mapa-de-etapas')
        ->assertOk()
        ->assertSee('Funil Dr. Eduardo')
        ->assertSee('Após a reestruturação do Kommo este mapa vira editável');
});

it('recepção não acessa as automações', function () {
    $this->actingAs(usuarioDasAutomacoes(UserRole::Recepcao));

    $this->get('/painel/automacoes/regras')->assertForbidden();
    $this->get('/painel/automacoes/simulador')->assertForbidden();
    $this->get('/painel/automacoes/mapa-de-etapas')->assertForbidden();
});

it('mapa de etapas sem token mostra só os IDs com aviso', function () {
    config()->set('kommo.token', null);

    $this->actingAs(usuarioDasAutomacoes());

    $this->get('/painel/automacoes/mapa-de-etapas')
        ->assertOk()
        ->assertSee('mostra só os IDs')
        ->assertSee('84709188');
});

it('simulador com lead de exemplo mostra o passo a passo e registra a execução simulada', function () {
    $regra = AutomationRule::query()->create(AutomacoesDemoSeeder::regras()['A6']);

    $this->actingAs(usuarioDasAutomacoes());

    Livewire::test(SimuladorAutomacao::class)
        ->fillForm([
            'regra_id' => $regra->id,
            'fonte' => 'exemplo',
            'exemplo' => 'eduardo_negociacao',
            'evento_tipo' => 'campo_alterado',
            'evento_campo' => 'data_assinatura',
            'evento_valor' => '10/09/2026',
        ])
        ->call('simular')
        ->assertSee('Fluxo cirurgias')
        ->assertSee('Iniciar pré-operatório')
        ->assertSee('Registrar execução simulada')
        ->call('registrar');

    $run = AutomationRun::query()->sole();

    expect($run->status)->toBe(StatusExecucaoAutomacao::Registrado)
        ->and($run->kommo_lead_id)->toBe(23809377)
        ->and($run->acoes)->toHaveCount(4)
        ->and($run->acoes[1]['payload'][0]['pipeline_id'])->toBe(11380984);
});

it('simulador com ID real sem token avisa de forma amigável', function () {
    config()->set('kommo.token', null);

    $regra = AutomationRule::query()->create(AutomacoesDemoSeeder::regras()['A3']);

    $this->actingAs(usuarioDasAutomacoes());

    Livewire::test(SimuladorAutomacao::class)
        ->fillForm([
            'regra_id' => $regra->id,
            'fonte' => 'kommo',
            'lead_id' => 123456,
        ])
        ->call('simular')
        ->assertNotified('Não foi possível ler o lead no Kommo')
        ->assertSet('resultado', null);
});

it('ação Modo troca o modo da regra', function () {
    $regra = AutomationRule::query()->create(AutomacoesDemoSeeder::regras()['A3']);

    $this->actingAs(usuarioDasAutomacoes());

    Livewire::test(ListAutomationRules::class)
        ->callTableAction('modo', $regra, data: ['modo' => ModoAutomacao::Ativo->value])
        ->assertHasNoTableActionErrors();

    expect($regra->refresh()->modo)->toBe(ModoAutomacao::Ativo);
});
