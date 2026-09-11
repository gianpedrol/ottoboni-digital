<?php

namespace App\Services\Financeiro;

use App\Enums\StatusFinanceiro;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Support\Financeiro;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Gera as parcelas de um recebível: N parcelas mensais a partir do
 * primeiro vencimento; a última absorve o arredondamento.
 */
class GeradorDeParcelas
{
    /**
     * @return Collection<int, ReceivableInstallment>
     */
    public function gerar(Receivable $receivable, int $parcelas, CarbonInterface|string $primeiroVencimento): Collection
    {
        $primeiro = CarbonImmutable::parse($primeiroVencimento)->startOfDay();
        $valores = Financeiro::dividir((float) $receivable->valor_total, $parcelas);

        $receivable->installments()->delete();

        return collect($valores)->map(fn (float $valor, int $i): ReceivableInstallment => $receivable->installments()->create([
            'numero' => $i + 1,
            'valor' => $valor,
            // addMonthsNoOverflow: vencimento dia 31 cai no último dia dos meses curtos.
            'vencimento' => $primeiro->addMonthsNoOverflow($i)->toDateString(),
            'status' => StatusFinanceiro::Aberto->value,
            'demo' => (bool) $receivable->demo,
        ]));
    }
}
