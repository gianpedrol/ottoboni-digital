<?php

use App\Enums\DoctorScope;
use App\Enums\TipoRegistroProntuario;
use App\Enums\UserRole;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\MedicalRecordsRelationManager;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__ . '/helpers.php';

beforeEach(function () {
    config()->set('painel.prototipo', true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->eduardo, $this->vanessa] = clinicaMedicos();
    $this->paciente = clinicaPaciente($this->eduardo, ['nome' => 'Paciente do Prontuário']);
});

function registroDeTeste(array $atributos = []): MedicalRecord
{
    return MedicalRecord::query()->create([
        'patient_id' => test()->paciente->id,
        'doctor_id' => test()->eduardo->id,
        'tipo' => TipoRegistroProntuario::Evolucao,
        'titulo' => 'Evolução de teste',
        'conteudo' => '<p>Sem intercorrências.</p>',
        ...$atributos,
    ]);
}

function prontuarioLivewire(): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(MedicalRecordsRelationManager::class, [
        'ownerRecord' => test()->paciente,
        'pageClass' => ViewPatient::class,
    ]);
}

it('registro assinado não pode ser editado nem excluído', function () {
    $admin = clinicaUsuario(UserRole::Admin);
    $this->actingAs($admin);

    $registro = registroDeTeste(['assinado_em' => now()]);

    expect($admin->can('update', $registro))->toBeFalse()
        ->and($admin->can('delete', $registro))->toBeFalse();

    prontuarioLivewire()
        ->assertTableActionHidden('edit', $registro)
        ->assertTableActionHidden('delete', $registro)
        ->assertTableActionHidden('assinar', $registro)
        ->assertTableActionVisible('view', $registro);

    // Trava também no model, fora da tela.
    expect(fn () => $registro->update(['titulo' => 'Alterado']))->toThrow(DomainException::class)
        ->and(fn () => $registro->delete())->toThrow(DomainException::class)
        ->and($registro->fresh()->titulo)->toBe('Evolução de teste');
});

it('assinar um rascunho grava assinado_em e registra auditoria', function () {
    $this->actingAs(clinicaUsuario(UserRole::Gestor));

    $registro = registroDeTeste();

    prontuarioLivewire()
        ->assertTableActionVisible('edit', $registro)
        ->callTableAction('assinar', $registro);

    expect($registro->fresh()->assinado_em)->not->toBeNull()
        ->and(AuditLog::query()->where('acao', 'assinou_registro_prontuario')->count())->toBe(1);
});

it('cria anamnese pelo prontuário com IMC calculado e dados cifrados', function () {
    $admin = clinicaUsuario(UserRole::Admin);
    $this->actingAs($admin);

    prontuarioLivewire()
        ->callTableAction('create', data: [
            'tipo' => TipoRegistroProntuario::Anamnese->value,
            'titulo' => 'Anamnese inicial',
            'dados' => [
                'queixa_principal' => 'Deseja rinoplastia estruturada',
                'peso' => 60,
                'altura' => 1.65,
            ],
        ])
        ->assertHasNoErrors();

    $registro = MedicalRecord::query()->firstOrFail();
    $bruto = DB::table('medical_records')->where('id', $registro->id)->value('dados');

    expect($registro->tipo)->toBe(TipoRegistroProntuario::Anamnese)
        ->and($registro->dados['imc'])->toEqual(22.0)
        ->and($registro->user_id)->toBe($admin->id)
        ->and($registro->doctor_id)->toBe($this->eduardo->id)
        ->and($bruto)->not->toContain('rinoplastia');
});

it('recepção não vê a aba nem os registros do prontuário', function () {
    registroDeTeste(['titulo' => 'Anamnese reservada']);

    $recepcao = clinicaUsuario(UserRole::Recepcao, DoctorScope::Eduardo);
    $this->actingAs($recepcao);

    $this->get("/painel/patients/{$this->paciente->id}")
        ->assertOk()
        ->assertSee('Paciente do Prontuário')
        ->assertDontSee('Anamnese reservada')
        ->assertDontSee('Linha do tempo');

    expect(MedicalRecordsRelationManager::canViewForRecord($this->paciente, ViewPatient::class))->toBeFalse()
        ->and($recepcao->can('viewAny', MedicalRecord::class))->toBeFalse();

    prontuarioLivewire()->assertForbidden();

    expect(AuditLog::query()->where('acao', 'abriu_prontuario')->count())->toBe(0);
});

it('abrir o prontuário registra auditoria só com o id do paciente', function () {
    $this->actingAs(clinicaUsuario(UserRole::Admin));

    registroDeTeste(['titulo' => 'Registro visível']);

    $this->get("/painel/patients/{$this->paciente->id}")
        ->assertOk()
        ->assertSee('Registro visível');

    $log = AuditLog::query()->where('acao', 'abriu_prontuario')->sole();

    expect($log->contexto)->toBe(['patient_id' => $this->paciente->id]);
});

function urlImpressaoDePrescricao(): string
{
    $registro = registroDeTeste([
        'tipo' => TipoRegistroProntuario::Prescricao,
        'titulo' => 'Prescrição pós-operatória',
        'conteudo' => 'Repouso relativo.',
        'dados' => ['itens' => [['medicamento' => 'Dipirona', 'dose' => '1 g', 'posologia' => '6/6h', 'duracao' => '5 dias']]],
    ]);

    return '/painel/patients/' . test()->paciente->id . "/prontuario/{$registro->id}/imprimir";
}

it('imprime prescrição para o admin, marcada como rascunho enquanto não assinada', function () {
    $this->actingAs(clinicaUsuario(UserRole::Admin))
        ->get(urlImpressaoDePrescricao())
        ->assertOk()
        ->assertSee('Dipirona')
        ->assertSee('Rascunho');
});

it('recepção não abre a impressão do prontuário', function () {
    $this->actingAs(clinicaUsuario(UserRole::Recepcao, DoctorScope::Eduardo))
        ->get(urlImpressaoDePrescricao())
        ->assertForbidden();
});
