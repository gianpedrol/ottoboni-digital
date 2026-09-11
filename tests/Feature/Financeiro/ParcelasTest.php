<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Filament\Resources\Receivables\Pages\CreateReceivable;
use App\Filament\Resources\Receivables\Pages\ViewReceivable;
use App\Filament\Resources\Receivables\RelationManagers\InstallmentsRelationManager;
use App\Models\Doctor;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Financeiro\GeradorDeParcelas;
use App\Support\Financeiro;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    $this->doctor = Doctor::query()->create([
        'nome' => 'Dr. Eduardo Ottoboni',
        'agente' => 'duda',
        'kommo_pipeline_id' => config('kommo.pipelines.duda'),
        'ativo' => true,
    ]);

    $this->actingAs(User::query()->create([
        'name' => 'Admin',
        'email' => 'admin-parcelas@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => UserRole::Admin,
        'doctor_scope' => DoctorScope::Ambos,
    ]));
});

it('divide em centavos e a última parcela absorve o arredondamento', function () {
    expect(Financeiro::dividir(1000, 3))->toBe([333.33, 333.33, 333.34])
        ->and(array_sum(Financeiro::dividir(18000.5, 7)))->toEqualWithDelta(18000.5, 0.001)
        ->and(Financeiro::dividir(450, 1))->toBe([450.0]);
});

it('gera as parcelas mensais e a soma bate com o total', function () {
    $receivable = Receivable::query()->create([
        'doctor_id' => $this->doctor->id,
        'descricao' => 'Contrato · Abdominoplastia',
        'valor_total' => 22000.10,
        'forma_pagamento' => 'boleto',
        'origem' => 'contrato',
    ]);

    $parcelas = app(GeradorDeParcelas::class)->gerar($receivable, 3, '2027-01-31');

    expect($parcelas)->toHaveCount(3)
        ->and(round((float) $receivable->installments()->sum('valor'), 2))->toBe(22000.10)
        ->and($receivable->installments()->pluck('valor')->map(fn ($v) => (float) $v)->all())->toBe([7333.36, 7333.36, 7333.38])
        // Vencimento dia 31 cai no último dia dos meses curtos.
        ->and($receivable->installments()->get()->map(fn ($p) => $p->vencimento->toDateString())->all())
        ->toBe(['2027-01-31', '2027-02-28', '2027-03-31']);
});

it('a tela de criação gera as parcelas a partir do nº e do 1º vencimento', function () {
    Livewire::test(CreateReceivable::class)
        ->fillForm([
            'doctor_id' => $this->doctor->id,
            'descricao' => 'Contrato · Rinoplastia',
            'valor_total' => 10000,
            'forma_pagamento' => 'cartao',
            'origem' => 'contrato',
            'parcelas' => 7,
            'primeiro_vencimento' => '2026-10-15',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $receivable = Receivable::query()->where('descricao', 'Contrato · Rinoplastia')->firstOrFail();

    expect($receivable->installments)->toHaveCount(7)
        ->and(round((float) $receivable->installments->sum('valor'), 2))->toBe(10000.0)
        ->and((float) $receivable->installments->last()->valor)->toBe(1428.58)
        ->and($receivable->installments->last()->vencimento->toDateString())->toBe('2027-04-15');
});

it('registrar pagamento baixa a parcela', function () {
    $receivable = Receivable::query()->create([
        'doctor_id' => $this->doctor->id,
        'descricao' => 'Consulta de cirurgia plástica',
        'valor_total' => 600,
        'forma_pagamento' => 'pix',
        'origem' => 'consulta',
    ]);

    app(GeradorDeParcelas::class)->gerar($receivable, 1, Financeiro::hoje()->subDays(5));
    $parcela = $receivable->installments()->firstOrFail();

    expect($receivable->fresh()->situacao()->value)->toBe('atrasado');

    Livewire::test(InstallmentsRelationManager::class, [
        'ownerRecord' => $receivable,
        'pageClass' => ViewReceivable::class,
    ])
        ->callTableAction('registrarPagamento', $parcela, data: [
            'pago_em' => Financeiro::hoje()->toDateString(),
            'valor_pago' => 600,
        ])
        ->assertHasNoTableActionErrors();

    $parcela->refresh();

    expect($parcela->status)->toBe('pago')
        ->and((float) $parcela->valor_pago)->toBe(600.0)
        ->and($receivable->fresh()->situacao()->value)->toBe('pago');
});
