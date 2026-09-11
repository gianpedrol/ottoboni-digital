<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\User;
use App\Services\Financeiro\Conciliador;
use App\Services\Financeiro\GeradorDeParcelas;
use App\Services\Financeiro\ImportadorOfx;
use App\Support\Financeiro;
use Database\Seeders\FinanceiroDemoSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    $this->actingAs(User::query()->create([
        'name' => 'Admin',
        'email' => 'admin-conciliacao@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => UserRole::Admin,
        'doctor_scope' => DoctorScope::Ambos,
    ]));

    $this->conta = BankAccount::query()->create(['apelido' => 'Conta movimento', 'banco' => 'Itaú']);
    $this->hoje = Financeiro::hoje();
});

function parcelaEmAberto(float $valor, string $vencimento): ReceivableInstallment
{
    $receivable = Receivable::query()->create([
        'descricao' => 'Toxina botulínica',
        'valor_total' => $valor,
        'forma_pagamento' => 'pix',
        'origem' => 'procedimento',
    ]);

    return app(GeradorDeParcelas::class)->gerar($receivable, 1, $vencimento)->first();
}

function contaEmAberto(float $valor, string $vencimento): Payable
{
    return Payable::query()->create([
        'fornecedor' => 'Medical Supply',
        'categoria' => 'materiais',
        'valor' => $valor,
        'vencimento' => $vencimento,
        'status' => 'aberto',
    ]);
}

it('sugere parcelas em aberto com o mesmo valor e data próxima para um crédito', function () {
    $certa = parcelaEmAberto(1800, $this->hoje->subDays(3)->toDateString());
    parcelaEmAberto(1800, $this->hoje->subDays(40)->toDateString());   // longe demais
    parcelaEmAberto(1750, $this->hoje->subDays(2)->toDateString());    // outro valor
    contaEmAberto(1800, $this->hoje->subDays(2)->toDateString());      // débito, não entra

    $credito = $this->conta->transactions()->create([
        'data' => $this->hoje->subDays(2)->toDateString(),
        'descricao' => 'PIX RECEBIDO CAMILA',
        'valor' => 1800,
    ]);

    $sugestoes = app(Conciliador::class)->sugestoes($credito);

    expect($sugestoes)->toHaveCount(1)
        ->and($sugestoes->first()->is($certa))->toBeTrue();
});

it('conciliar um crédito liga o lançamento e dá baixa na parcela com a data do banco', function () {
    $parcela = parcelaEmAberto(1800, $this->hoje->subDays(3)->toDateString());

    $credito = $this->conta->transactions()->create([
        'data' => $this->hoje->subDays(1)->toDateString(),
        'descricao' => 'PIX RECEBIDO CAMILA',
        'valor' => 1800,
    ]);

    app(Conciliador::class)->conciliar($credito, $parcela);

    $parcela->refresh();
    $credito->refresh();

    expect($parcela->status)->toBe('pago')
        ->and($parcela->pago_em->toDateString())->toBe($this->hoje->subDays(1)->toDateString())
        ->and((float) $parcela->valor_pago)->toBe(1800.0)
        ->and($credito->estaConciliado())->toBeTrue()
        ->and($credito->conciliavel->is($parcela))->toBeTrue();
});

it('não concilia débito com parcela a receber', function () {
    $parcela = parcelaEmAberto(500, $this->hoje->toDateString());

    $debito = $this->conta->transactions()->create([
        'data' => $this->hoje->toDateString(),
        'descricao' => 'TARIFA',
        'valor' => -500,
    ]);

    app(Conciliador::class)->conciliar($debito, $parcela);
})->throws(InvalidArgumentException::class);

it('a ação Conciliar da tela do extrato dá baixa na conta a pagar', function () {
    $conta = contaEmAberto(4870.35, $this->hoje->subDays(4)->toDateString());

    $debito = $this->conta->transactions()->create([
        'data' => $this->hoje->subDays(3)->toDateString(),
        'descricao' => 'PAG BOLETO MEDICAL SUPPLY',
        'valor' => -4870.35,
    ]);

    Livewire::test(ListBankTransactions::class)
        ->callTableAction('conciliar', $debito, data: ['item' => 'payable:' . $conta->id])
        ->assertHasNoTableActionErrors();

    expect($conta->fresh()->status)->toBe('pago')
        ->and($conta->fresh()->pago_em->toDateString())->toBe($this->hoje->subDays(3)->toDateString())
        ->and($debito->fresh()->conciliavel_id)->toBe($conta->id)
        ->and($debito->fresh()->conciliado_em)->not->toBeNull();
});

it('depois do seeder, o OFX de exemplo tem sugestão para cada recebimento e pagamento', function () {
    $this->seed(FinanceiroDemoSeeder::class);

    $itau = BankAccount::query()->where('demo', true)->where('banco', 'Itaú')->firstOrFail();

    app(ImportadorOfx::class)->importar($itau, file_get_contents(storage_path('app/demo/extrato-exemplo.ofx')));

    $semVinculoEsperado = ['OTB202609080002', 'OTB202609090001']; // tarifa e rendimento

    BankTransaction::query()->where('fitid', 'like', 'OTB%')->get()->each(function (BankTransaction $t) use ($semVinculoEsperado) {
        $opcoes = app(Conciliador::class)->opcoes($t);

        if (in_array($t->fitid, $semVinculoEsperado, true)) {
            expect($opcoes)->toBeEmpty();
        } else {
            expect($opcoes)->not->toBeEmpty("{$t->fitid} {$t->descricao} sem sugestão");
        }
    });
});
