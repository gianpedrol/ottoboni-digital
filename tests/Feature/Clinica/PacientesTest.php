<?php

use App\Enums\DoctorScope;
use App\Enums\EtapaCanonica;
use App\Enums\KommoSyncStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Models\Patient;
use App\Services\Pacientes\SincronizadorKommo;
use App\Support\MapaDeEtapas;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

require_once __DIR__ . '/helpers.php';

beforeEach(function () {
    config()->set('painel.prototipo', true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->eduardo, $this->vanessa] = clinicaMedicos();
});

function dadosDoFormulario(array $sobrescrever = []): array
{
    return [
        'nome' => 'Ana Beatriz Lima',
        'telefone' => '(41) 99876-5432',
        'email' => 'ana@exemplo.com.br',
        'cpf' => '529.982.247-25',
        'data_nascimento' => '1990-05-12',
        'sexo' => 'F',
        'procedimento_interesse' => 'Rinoplastia',
        'origem' => 'Instagram',
        'abrir_atendimento' => true,
        ...$sobrescrever,
    ];
}

it('lista, cadastro, ficha e edição renderizam para o admin', function () {
    $patient = clinicaPaciente($this->eduardo, ['nome' => 'Maria Renderizada', 'cpf' => '529.982.247-25']);

    $this->actingAs(clinicaUsuario(UserRole::Admin));

    $this->get('/painel/patients')->assertOk()->assertSee('Maria Renderizada');
    $this->get('/painel/patients/create')->assertOk();
    $this->get("/painel/patients/{$patient->id}")
        ->assertOk()
        ->assertSee('Maria Renderizada')
        ->assertSee('Prontuário')
        ->assertSee('***.***.***-25')
        ->assertDontSee('529.982.247-25')
        ->assertDontSee('52998224725');
    $this->get("/painel/patients/{$patient->id}/edit")->assertOk();
});

it('recepção do Dr. Eduardo não vê paciente da Dra. Vanessa, nem por URL direta', function () {
    $dele = clinicaPaciente($this->eduardo, ['nome' => 'Paciente do Eduardo']);
    $dela = clinicaPaciente($this->vanessa, ['nome' => 'Paciente da Vanessa']);

    $this->actingAs(clinicaUsuario(UserRole::Recepcao, DoctorScope::Eduardo));

    Livewire::test(ListPatients::class)
        ->assertCanSeeTableRecords([$dele])
        ->assertCanNotSeeTableRecords([$dela]);

    $this->get("/painel/patients/{$dela->id}")->assertNotFound();
    $this->get("/painel/patients/{$dela->id}/edit")->assertNotFound();
    $this->get("/painel/patients/{$dele->id}")->assertOk();
});

it('cadastrar paciente monta o payload simulado e não chama o Kommo', function () {
    Http::fake();

    $this->actingAs(clinicaUsuario(UserRole::Admin));

    Livewire::test(CreatePatient::class)
        ->fillForm(dadosDoFormulario(['doctor_id' => $this->eduardo->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    Http::assertNothingSent();

    $patient = Patient::query()->where('nome', 'Ana Beatriz Lima')->firstOrFail();
    $requisicoes = $patient->kommo_sync_payload['requisicoes'];
    $pipeline = (int) config('kommo.pipelines.duda');

    expect($patient->telefone)->toBe('+55 41 99876-5432')
        ->and($patient->kommo_sync_status)->toBe(KommoSyncStatus::Simulado)
        ->and($patient->kommo_contact_id)->toBeNull()
        ->and($patient->kommo_sync_payload['modo'])->toBe('simulado')
        ->and($requisicoes)->toHaveCount(2);

    expect($requisicoes[0]['metodo'])->toBe('POST')
        ->and($requisicoes[0]['endpoint'])->toBe('/api/v4/contacts')
        ->and($requisicoes[0]['corpo'])->toBe([[
            'name' => 'Ana Beatriz Lima',
            'first_name' => 'Ana',
            'last_name' => 'Beatriz Lima',
            'custom_fields_values' => [
                ['field_code' => 'PHONE', 'values' => [['value' => '+5541998765432', 'enum_code' => 'WORK']]],
                ['field_code' => 'EMAIL', 'values' => [['value' => 'ana@exemplo.com.br', 'enum_code' => 'WORK']]],
            ],
        ]]);

    expect($requisicoes[1]['metodo'])->toBe('POST')
        ->and($requisicoes[1]['endpoint'])->toBe('/api/v4/leads')
        ->and($requisicoes[1]['corpo'])->toBe([[
            'name' => 'Ana Beatriz Lima — Rinoplastia',
            'pipeline_id' => $pipeline,
            'status_id' => MapaDeEtapas::destino($pipeline, EtapaCanonica::Novo),
            '_embedded' => ['contacts' => [['id' => SincronizadorKommo::ID_CONTATO_A_CRIAR]]],
        ]]);

    // CPF nunca vai no payload.
    expect(json_encode($patient->kommo_sync_payload))->not->toContain('52998224725');
});

it('sem "abrir atendimento" o payload tem só o contato', function () {
    Http::fake();

    $this->actingAs(clinicaUsuario(UserRole::Admin));

    Livewire::test(CreatePatient::class)
        ->fillForm(dadosDoFormulario(['doctor_id' => $this->vanessa->id, 'abrir_atendimento' => false, 'email' => null]))
        ->call('create')
        ->assertHasNoFormErrors();

    Http::assertNothingSent();

    $requisicoes = Patient::query()->firstOrFail()->kommo_sync_payload['requisicoes'];

    expect($requisicoes)->toHaveCount(1)
        ->and($requisicoes[0]['corpo'][0]['custom_fields_values'])->toHaveCount(1);
});

it('bloqueia telefone já cadastrado, em qualquer formato', function () {
    clinicaPaciente($this->eduardo, ['telefone' => '+55 41 99876-5432']);

    $this->actingAs(clinicaUsuario(UserRole::Admin));

    Livewire::test(CreatePatient::class)
        ->fillForm(dadosDoFormulario(['doctor_id' => $this->eduardo->id, 'telefone' => '(41) 99876-5432']))
        ->call('create')
        ->assertHasFormErrors(['telefone']);

    expect(Patient::query()->count())->toBe(1);
});

it('editar o próprio paciente não acusa o telefone dele como duplicado', function () {
    $patient = clinicaPaciente($this->eduardo, ['telefone' => '+55 41 99876-5432', 'cpf' => '529.982.247-25']);

    $this->actingAs(clinicaUsuario(UserRole::Admin));

    Livewire::test(EditPatient::class, ['record' => $patient->getRouteKey()])
        ->assertSchemaStateSet(['telefone' => '(41) 99876-5432', 'cpf' => '529.982.247-25'])
        ->fillForm(['nome' => 'Nome Alterado'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($patient->fresh()->nome)->toBe('Nome Alterado')
        ->and($patient->fresh()->kommo_sync_status)->toBe(KommoSyncStatus::Simulado);
});

it('rejeita CPF com dígito verificador errado', function () {
    $this->actingAs(clinicaUsuario(UserRole::Admin));

    Livewire::test(CreatePatient::class)
        ->fillForm(dadosDoFormulario(['doctor_id' => $this->eduardo->id, 'cpf' => '529.982.247-26']))
        ->call('create')
        ->assertHasFormErrors(['cpf']);
});

it('guarda o CPF cifrado e fora de toArray()', function () {
    $patient = clinicaPaciente($this->eduardo, ['cpf' => '529.982.247-25']);

    $bruto = DB::table('patients')->where('id', $patient->id)->value('cpf');

    expect($bruto)->not->toContain('52998224725')
        ->and(Crypt::decryptString($bruto))->toBe('52998224725')
        ->and($patient->fresh()->cpfMascarado())->toBe('***.***.***-25')
        ->and($patient->fresh()->toArray())->not->toHaveKey('cpf');
});
