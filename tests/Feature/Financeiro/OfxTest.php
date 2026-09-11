<?php

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\Financeiro\ImportadorOfx;

beforeEach(function () {
    $this->conta = BankAccount::query()->create([
        'apelido' => 'Conta movimento',
        'banco' => 'Itaú',
        'saldo_inicial' => 1000,
    ]);

    $this->arquivo = file_get_contents(storage_path('app/demo/extrato-exemplo.ofx'));
});

it('lê os blocos STMTTRN do arquivo de exemplo', function () {
    $lancamentos = app(ImportadorOfx::class)->ler($this->arquivo);

    expect($lancamentos)->toHaveCount(9)
        ->and($lancamentos[0])->toBe([
            'data' => '2026-09-01',
            'valor' => 1800.0,
            'fitid' => 'OTB202609010001',
            'descricao' => 'PIX RECEBIDO CAMILA R SOUZA',
        ])
        ->and($lancamentos[3]['valor'])->toBe(-4870.35);
});

it('importar o mesmo OFX duas vezes não duplica FITID', function () {
    $importador = app(ImportadorOfx::class);

    $primeira = $importador->importar($this->conta, $this->arquivo);
    $segunda = $importador->importar($this->conta, $this->arquivo);

    expect($primeira)->toBe(['importados' => 9, 'ignorados' => 0])
        ->and($segunda)->toBe(['importados' => 0, 'ignorados' => 9])
        ->and(BankTransaction::query()->count())->toBe(9)
        ->and(BankTransaction::query()->where('demo', true)->count())->toBe(9)
        ->and(BankTransaction::query()->distinct()->count('fitid'))->toBe(9)
        ->and($this->conta->saldoAtual())->toEqualWithDelta(1000 + 1800 + 600 + 2200 - 4870.35 + 3600 - 2500 + 450 - 89.90 + 12.47, 0.001);
});

it('o mesmo FITID em outra conta é outro lançamento', function () {
    $outra = BankAccount::query()->create(['apelido' => 'Inter', 'banco' => 'Banco Inter']);

    app(ImportadorOfx::class)->importar($this->conta, $this->arquivo);
    app(ImportadorOfx::class)->importar($outra, $this->arquivo);

    expect(BankTransaction::query()->count())->toBe(18);
});

it('aceita SGML sem tag de fechamento, vírgula decimal, FITID repetido e lançamento sem FITID', function () {
    $ofx = <<<'OFX'
        OFXHEADER:100
        <OFX><BANKTRANLIST>
        <STMTTRN>
        <DTPOSTED>20260815
        <TRNAMT>-150,25
        <FITID>ABC1
        <MEMO>TARIFA
        <STMTTRN>
        <DTPOSTED>20260815
        <TRNAMT>-150,25
        <FITID>ABC1
        <MEMO>TARIFA
        <STMTTRN>
        <DTPOSTED>20260816120000
        <TRNAMT>99.90
        <NAME>SEM FITID
        </BANKTRANLIST></OFX>
        OFX;

    $resultado = app(ImportadorOfx::class)->importar($this->conta, $ofx);

    expect($resultado)->toBe(['importados' => 2, 'ignorados' => 1])
        ->and((float) BankTransaction::query()->where('fitid', 'ABC1')->value('valor'))->toBe(-150.25)
        ->and(BankTransaction::query()->where('descricao', 'SEM FITID')->value('data')->toDateString())->toBe('2026-08-16');

    // Sem FITID, a reimportação também não duplica.
    expect(app(ImportadorOfx::class)->importar($this->conta, $ofx))->toBe(['importados' => 0, 'ignorados' => 3]);
});
