<?php

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Enums\UserRole;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Models\Appointment;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\ConflitoDeHorario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\Agenda\Cenario;

beforeEach(function () {
    config()->set('painel.prototipo', true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::fake();

    $this->eduardo = Cenario::eduardo();
    $this->paciente = Cenario::paciente($this->eduardo);
    $this->agenda = app(AgendaService::class);
});

/**
 * @return array<string, mixed>
 */
function dadosDeAgendamento(object $ctx, CarbonImmutable $inicio, int $minutos = 30): array
{
    return [
        'patient_id' => $ctx->paciente->id,
        'doctor_id' => $ctx->eduardo->id,
        'tipo' => TipoAgendamento::Consulta,
        'inicio' => $inicio,
        'fim' => $inicio->addMinutes($minutos),
    ];
}

it('bloqueia dois agendamentos sobrepostos do mesmo médico', function () {
    $inicio = Cenario::segundaQueVem(10);
    Cenario::agendamento($this->eduardo, $this->paciente, $inicio, 60);

    expect(fn () => $this->agenda->agendar(dadosDeAgendamento($this, $inicio->addMinutes(30))))
        ->toThrow(ConflitoDeHorario::class);

    // Encostado no fim do outro não é conflito.
    $this->agenda->agendar(dadosDeAgendamento($this, $inicio->addMinutes(60)));

    expect(Appointment::query()->count())->toBe(2);
});

it('cancelado e remarcado não ocupam o horário', function () {
    $inicio = Cenario::segundaQueVem(14);
    Cenario::agendamento($this->eduardo, $this->paciente, $inicio, 60, StatusAgendamento::Cancelado);
    Cenario::agendamento($this->eduardo, $this->paciente, $inicio, 60, StatusAgendamento::Remarcado);

    $resultado = $this->agenda->agendar(dadosDeAgendamento($this, $inicio));

    expect($resultado->agendamento->status)->toBe(StatusAgendamento::Agendado);
});

it('outro médico no mesmo horário não é conflito', function () {
    $inicio = Cenario::segundaQueVem(9);
    Cenario::agendamento($this->eduardo, $this->paciente, $inicio, 60);

    $vanessa = Cenario::vanessa();

    $resultado = $this->agenda->agendar([
        ...dadosDeAgendamento($this, $inicio),
        'doctor_id' => $vanessa->id,
    ]);

    expect($resultado->agendamento->doctor_id)->toBe($vanessa->id);
});

it('o formulário mostra o conflito no campo de horário', function () {
    $inicio = Cenario::segundaQueVem(10);
    Cenario::agendamento($this->eduardo, $this->paciente, $inicio, 60);

    $this->actingAs(Cenario::usuario(UserRole::Admin));

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'patient_id' => $this->paciente->id,
            'doctor_id' => $this->eduardo->id,
            'tipo' => 'consulta',
            'data' => $inicio->format('Y-m-d'),
            'hora' => '10:30',
            'duracao_min' => 30,
        ])
        ->call('create')
        ->assertHasFormErrors(['hora']);

    expect(Appointment::query()->count())->toBe(1);
});

it('agendar pelo formulário grava em UTC e simula a atualização do lead', function () {
    $inicio = Cenario::segundaQueVem(15);

    $this->actingAs(Cenario::usuario(UserRole::Admin));

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'patient_id' => $this->paciente->id,
            'doctor_id' => $this->eduardo->id,
            'tipo' => 'consulta',
            'data' => $inicio->format('Y-m-d'),
            'hora' => '15:00',
            'duracao_min' => 45,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Simulado — no painel real o Kommo seria atualizado');

    $agendamento = Appointment::query()->sole();

    expect($agendamento->inicioLocal()->format('Y-m-d H:i'))->toBe($inicio->format('Y-m-d') . ' 15:00')
        ->and($agendamento->inicio->getTimestamp())->toBe($inicio->getTimestamp())
        ->and($agendamento->duracaoMin())->toBe(45)
        ->and($agendamento->kommo_lead_id)->toBe(777001);

    Http::assertNothingSent();
});

it('agendar monta o PATCH com Próxima consulta e a etapa "consulta agendada" do médico', function () {
    $inicio = Cenario::segundaQueVem(11);

    $efeito = $this->agenda->agendar(dadosDeAgendamento($this, $inicio))->efeito;

    expect($efeito->simulado)->toBeTrue()
        ->and($efeito->endpoint())->toBe('/api/v4/leads/777001')
        ->and($efeito->corpo['status_id'])->toBe(54917479)
        ->and($efeito->corpo['pipeline_id'])->toBe((int) config('kommo.pipelines.duda'))
        ->and($efeito->corpo['custom_fields_values'][0]['field_name'])->toBe('Próxima consulta')
        ->and($efeito->corpo['custom_fields_values'][0]['values'][0]['value'])->toBe($inicio->getTimestamp())
        ->and(array_keys($efeito->automacoes))->toBe(['A2']);

    Http::assertNothingSent();
});

it('remarcar marca o antigo como remarcado e cria o novo', function () {
    $antigo = Cenario::agendamento($this->eduardo, $this->paciente, Cenario::segundaQueVem(10), 60);
    $novoInicio = Cenario::segundaQueVem(16);

    $resultado = $this->agenda->remarcar($antigo, $novoInicio);

    $novo = $resultado->agendamento;

    expect($antigo->refresh()->status)->toBe(StatusAgendamento::Remarcado)
        ->and($novo->id)->not->toBe($antigo->id)
        ->and($novo->status)->toBe(StatusAgendamento::Agendado)
        ->and($novo->patient_id)->toBe($antigo->patient_id)
        ->and($novo->inicioLocal()->format('H:i'))->toBe('16:00')
        ->and($novo->duracaoMin())->toBe(60)
        ->and($novo->observacoes)->toContain('Remarcado de')
        ->and($resultado->efeito->evento)->toBe('remarcado')
        ->and(Appointment::query()->count())->toBe(2);

    Http::assertNothingSent();
});

it('remarcar para um horário ocupado é bloqueado e não mexe no antigo', function () {
    $antigo = Cenario::agendamento($this->eduardo, $this->paciente, Cenario::segundaQueVem(10));
    Cenario::agendamento($this->eduardo, $this->paciente, Cenario::segundaQueVem(16), 60);

    expect(fn () => $this->agenda->remarcar($antigo, Cenario::segundaQueVem(16, 30)))
        ->toThrow(ConflitoDeHorario::class);

    expect($antigo->refresh()->status)->toBe(StatusAgendamento::Agendado)
        ->and(Appointment::query()->count())->toBe(2);
});

it('marcar faltou simula Comparecimento = Faltou (A5) sem chamar o Kommo', function () {
    $agendamento = Cenario::agendamento($this->eduardo, $this->paciente, CarbonImmutable::now(Appointment::fuso())->setTime(8, 0));

    $efeito = $this->agenda->mudarStatus($agendamento, StatusAgendamento::Faltou)->efeito;

    expect($agendamento->refresh()->status)->toBe(StatusAgendamento::Faltou)
        ->and($efeito->simulado)->toBeTrue()
        ->and($efeito->corpo)->toBe([
            'custom_fields_values' => [[
                'field_id' => null,
                'field_name' => 'Comparecimento',
                'values' => [['value' => 'Faltou']],
            ]],
        ])
        ->and(array_keys($efeito->automacoes))->toBe(['A5'])
        ->and($efeito->abrirProntuario)->toBeFalse();

    Http::assertNothingSent();
});

it('marcar realizado simula Comparecimento = Compareceu (A3) e oferece o prontuário', function () {
    $agendamento = Cenario::agendamento($this->eduardo, $this->paciente, CarbonImmutable::now(Appointment::fuso())->setTime(8, 0));

    $efeito = $this->agenda->mudarStatus($agendamento, StatusAgendamento::Realizado)->efeito;

    expect($efeito->corpo['custom_fields_values'][0]['values'][0]['value'])->toBe('Compareceu')
        ->and(array_keys($efeito->automacoes))->toBe(['A3'])
        ->and($efeito->abrirProntuario)->toBeTrue()
        ->and($efeito->texto())->toContain('A3');

    Http::assertNothingSent();
});

it('paciente sem lead no Kommo não gera PATCH', function () {
    $semLead = Cenario::paciente($this->eduardo, 'Sem Lead', null);
    $agendamento = Cenario::agendamento($this->eduardo, $semLead, CarbonImmutable::now(Appointment::fuso())->setTime(8, 0));

    $efeito = $this->agenda->mudarStatus($agendamento, StatusAgendamento::Faltou)->efeito;

    expect($efeito->temEnvio())->toBeFalse()
        ->and($efeito->corpo)->toBe([]);
});

it('não marca comparecimento de agendamento futuro', function () {
    $agendamento = Cenario::agendamento($this->eduardo, $this->paciente, Cenario::segundaQueVem(10));

    expect(fn () => $this->agenda->mudarStatus($agendamento, StatusAgendamento::Faltou))
        ->toThrow(DomainException::class);
});

it('as ações da página marcam falta e mostram o efeito simulado', function () {
    $agendamento = Cenario::agendamento($this->eduardo, $this->paciente, CarbonImmutable::now(Appointment::fuso())->setTime(8, 0));

    $this->actingAs(Cenario::usuario(UserRole::Admin));

    Livewire::test(Agenda::class)
        ->callAction('faltou', arguments: ['id' => $agendamento->id])
        ->assertNotified('Simulado — no painel real o Kommo seria atualizado');

    expect($agendamento->refresh()->status)->toBe(StatusAgendamento::Faltou);

    Http::assertNothingSent();
});

it('o modal de novo agendamento da página cria no horário clicado', function () {
    $inicio = Cenario::segundaQueVem(8, 30);

    $this->actingAs(Cenario::usuario(UserRole::Admin));

    Livewire::test(Agenda::class)
        ->callAction('novo', data: [
            'patient_id' => $this->paciente->id,
        ], arguments: [
            'data' => $inicio->format('Y-m-d'),
            'hora' => '08:30',
            'medico' => $this->eduardo->id,
        ])
        ->assertHasNoActionErrors();

    $agendamento = Appointment::query()->sole();

    expect($agendamento->inicioLocal()->format('Y-m-d H:i'))->toBe($inicio->format('Y-m-d H:i'))
        ->and($agendamento->doctor_id)->toBe($this->eduardo->id);
});

it('remarcar pela página cria o novo e marca o antigo', function () {
    $antigo = Cenario::agendamento($this->eduardo, $this->paciente, Cenario::segundaQueVem(10));
    $novaData = Cenario::segundaQueVem()->addDay();

    $this->actingAs(Cenario::usuario(UserRole::Admin));

    Livewire::test(Agenda::class)
        ->callAction('remarcar', data: [
            'data' => $novaData->format('Y-m-d'),
            'hora' => '11:00',
            'duracao_min' => 30,
        ], arguments: ['id' => $antigo->id])
        ->assertHasNoActionErrors();

    expect($antigo->refresh()->status)->toBe(StatusAgendamento::Remarcado)
        ->and(Appointment::query()->where('status', 'agendado')->sole()->inicioLocal()->format('Y-m-d H:i'))
        ->toBe($novaData->format('Y-m-d') . ' 11:00');
});
