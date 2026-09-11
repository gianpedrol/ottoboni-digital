<?php

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Service;
use Database\Seeders\FinanceiroDemoSeeder;

function contagemFinanceiro(): array
{
    return [
        'services' => Service::query()->count(),
        'receivables' => Receivable::query()->count(),
        'installments' => ReceivableInstallment::query()->count(),
        'payables' => Payable::query()->count(),
        'accounts' => BankAccount::query()->count(),
        'transactions' => BankTransaction::query()->count(),
        'soma_parcelas' => round((float) ReceivableInstallment::query()->sum('valor'), 2),
    ];
}

it('roda duas vezes sem duplicar e sem apagar o que não é demonstração', function () {
    $doctor = Doctor::query()->create([
        'nome' => 'Dr. Eduardo Ottoboni',
        'agente' => 'duda',
        'kommo_pipeline_id' => config('kommo.pipelines.duda'),
        'ativo' => true,
    ]);

    $real = Service::query()->create([
        'doctor_id' => $doctor->id,
        'nome' => 'Serviço real',
        'tipo' => 'consulta',
        'valor' => 700,
        'demo' => false,
    ]);

    $this->seed(FinanceiroDemoSeeder::class);
    $primeira = contagemFinanceiro();

    $this->seed(FinanceiroDemoSeeder::class);
    $segunda = contagemFinanceiro();

    expect($segunda)->toBe($primeira)
        ->and(Service::query()->whereKey($real->id)->exists())->toBeTrue()
        ->and(Service::query()->where('demo', true)->count())->toBe(16)
        ->and(BankAccount::query()->count())->toBe(2)
        ->and(Doctor::query()->count())->toBe(2);
});

it('gera dados coerentes: parcelas somam o total, extrato quase todo conciliado', function () {
    $this->seed(FinanceiroDemoSeeder::class);

    Receivable::query()->with('installments')->get()->each(function (Receivable $r) {
        expect(round((float) $r->installments->sum('valor'), 2))->toBe(round((float) $r->valor_total, 2));
    });

    $lancamentos = BankTransaction::query()->count();
    $conciliados = BankTransaction::query()->whereNotNull('conciliado_em')->count();

    expect($lancamentos)->toBeGreaterThan(100)
        ->and($conciliados / $lancamentos)->toBeGreaterThan(0.7)
        ->and(ReceivableInstallment::query()->atrasadas()->count())->toBeGreaterThan(0)
        ->and(Payable::query()->where('recorrente', true)->count())->toBe(100);
});

it('usa os pacientes de demonstração quando existem', function () {
    $doctor = Doctor::query()->create([
        'nome' => 'Dra. Vanessa Ottoboni',
        'agente' => 'luna',
        'kommo_pipeline_id' => config('kommo.pipelines.luna'),
        'ativo' => true,
    ]);

    $paciente = Patient::query()->create([
        'nome' => 'Paciente de Demonstração',
        'telefone' => '15999990000',
        'doctor_id' => $doctor->id,
        'demo' => true,
    ]);

    $this->seed(FinanceiroDemoSeeder::class);

    expect(Receivable::query()->where('patient_id', $paciente->id)->count())->toBeGreaterThan(0);
});
