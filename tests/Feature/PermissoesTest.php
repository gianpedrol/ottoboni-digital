<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use App\Support\SelecaoMedico;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');

    Doctor::query()->create([
        'nome' => 'Dr. Eduardo Ottoboni',
        'agente' => 'duda',
        'kommo_pipeline_id' => config('kommo.pipelines.duda'),
        'ativo' => true,
    ]);

    Doctor::query()->create([
        'nome' => 'Dra. Vanessa Ottoboni',
        'agente' => 'luna',
        'kommo_pipeline_id' => config('kommo.pipelines.luna'),
        'ativo' => true,
    ]);
});

function criarUsuario(UserRole $role, DoctorScope $scope): User
{
    return User::query()->create([
        'name' => 'Teste',
        'email' => uniqid() . '@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => $role,
        'doctor_scope' => $scope,
    ]);
}

it('recepção da Dra. Vanessa só enxerga o pipeline da Luna', function () {
    $user = criarUsuario(UserRole::Recepcao, DoctorScope::Vanessa);

    expect($user->allowedPipelineIds())->toBe([(int) config('kommo.pipelines.luna')]);
});

it('recepção não amplia o escopo pedindo o outro médico no filtro', function () {
    $user = criarUsuario(UserRole::Recepcao, DoctorScope::Vanessa);

    // Pediu "duda" (ex.: manipulando a URL) — recebe só o que pode ver.
    $pipelines = SelecaoMedico::pipelineIds($user, 'duda');

    expect($pipelines)->toBe([(int) config('kommo.pipelines.luna')]);
});

it('admin e gestor enxergam os dois pipelines', function () {
    $admin = criarUsuario(UserRole::Admin, DoctorScope::Ambos);
    $gestor = criarUsuario(UserRole::Gestor, DoctorScope::Eduardo);

    // Gestor vê os dois médicos mesmo com escopo preenchido — o escopo só
    // restringe o papel recepção.
    expect($admin->allowedPipelineIds())->toHaveCount(2)
        ->and($gestor->allowedPipelineIds())->toHaveCount(2);
});

it('recepção de um médico recebe 403 ao abrir ficha do outro por URL direta', function () {
    $user = criarUsuario(UserRole::Recepcao, DoctorScope::Eduardo);

    // Lead pertence ao pipeline da Luna (Dra. Vanessa).
    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/987*' => Http::response(fakeLead([
            'id' => 987,
            'pipeline_id' => (int) config('kommo.pipelines.luna'),
        ]), 200),
    ]);

    $this->actingAs($user)
        ->get('/painel/atendimentos/987')
        ->assertForbidden();
});

it('recepção abre normalmente a ficha de lead do próprio médico', function () {
    $user = criarUsuario(UserRole::Recepcao, DoctorScope::Vanessa);

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/pipelines*' => Http::response(kommoFixture('pipelines'), 200),
        '*/users*' => Http::response(kommoPage([['id' => 11, 'name' => 'Equipe']], 'users'), 200),
        '*/contacts*' => Http::response(kommoPage([], 'contacts'), 200),
        '*/tasks*' => Http::response(kommoPage([], 'tasks'), 200),
        '*/leads/987*' => Http::response(fakeLead([
            'id' => 987,
            'name' => 'IG @paciente_luna',
            'pipeline_id' => (int) config('kommo.pipelines.luna'),
            'status_id' => 61001,
        ]), 200),
    ]);

    $this->actingAs($user)
        ->get('/painel/atendimentos/987')
        ->assertOk()
        ->assertSee('IG @paciente_luna');
});

it('gestor não acessa usuários nem configurações', function () {
    $gestor = criarUsuario(UserRole::Gestor, DoctorScope::Ambos);

    $this->actingAs($gestor)->get('/painel/users')->assertForbidden();
    $this->actingAs($gestor)->get('/painel/configuracoes')->assertForbidden();
});

it('admin acessa a administração de usuários', function () {
    $admin = criarUsuario(UserRole::Admin, DoctorScope::Ambos);

    $this->actingAs($admin)->get('/painel/users')->assertOk();
});

it('abrir a ficha registra auditoria', function () {
    $user = criarUsuario(UserRole::Admin, DoctorScope::Ambos);

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
        '*/leads/pipelines*' => Http::response(kommoFixture('pipelines'), 200),
        '*/users*' => Http::response(kommoPage([], 'users'), 200),
        '*/contacts*' => Http::response(kommoPage([], 'contacts'), 200),
        '*/tasks*' => Http::response(kommoPage([], 'tasks'), 200),
        '*/leads/555*' => Http::response(fakeLead(['id' => 555]), 200),
    ]);

    $this->actingAs($user)->get('/painel/atendimentos/555')->assertOk();

    expect(\App\Models\AuditLog::query()->where('acao', 'abriu_ficha')->count())->toBe(1);
});
