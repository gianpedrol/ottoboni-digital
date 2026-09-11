<?php

namespace App\Services\Financeiro;

use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\ReceivableInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Conciliação manual: liga um lançamento do extrato a uma parcela a
 * receber (crédito) ou a uma conta a pagar (débito) e dá baixa no item.
 */
class Conciliador
{
    /** Janela, em dias, entre a data do lançamento e o vencimento. */
    public const JANELA_DIAS = 10;

    /**
     * Itens em aberto com o mesmo valor e vencimento próximo, do mais
     * perto para o mais longe da data do lançamento.
     *
     * @return Collection<int, ReceivableInstallment|Payable>
     */
    public function sugestoes(BankTransaction $lancamento): Collection
    {
        $valor = abs((float) $lancamento->valor);
        $data = CarbonImmutable::parse($lancamento->data);
        $de = $data->subDays(self::JANELA_DIAS)->toDateString();
        $ate = $data->addDays(self::JANELA_DIAS)->toDateString();

        $query = $lancamento->ehCredito()
            ? ReceivableInstallment::query()->with('receivable.patient')->emAberto()
            : Payable::query()->emAberto();

        /** @var Collection<int, ReceivableInstallment|Payable> $itens */
        $itens = $query
            ->whereBetween('valor', [$valor - 0.005, $valor + 0.005])
            ->whereBetween('vencimento', [$de, $ate])
            ->get();

        return $itens
            ->sortBy(fn (ReceivableInstallment|Payable $item): float => abs($item->vencimento->diffInDays($data)))
            ->values();
    }

    /**
     * Opções para o select do modal, com chave "tipo:id".
     *
     * @return array<string, string>
     */
    public function opcoes(BankTransaction $lancamento): array
    {
        return $this->sugestoes($lancamento)
            ->mapWithKeys(fn (ReceivableInstallment|Payable $item): array => [$this->chave($item) => $item->rotulo()])
            ->all();
    }

    public function chave(ReceivableInstallment|Payable $item): string
    {
        return ($item instanceof Payable ? 'payable' : 'installment') . ':' . $item->getKey();
    }

    public function resolver(string $chave): ReceivableInstallment|Payable|null
    {
        [$tipo, $id] = array_pad(explode(':', $chave, 2), 2, null);

        return match ($tipo) {
            'installment' => ReceivableInstallment::query()->find($id),
            'payable' => Payable::query()->find($id),
            default => null,
        };
    }

    public function conciliar(BankTransaction $lancamento, ReceivableInstallment|Payable $item): void
    {
        if ($lancamento->estaConciliado()) {
            throw new InvalidArgumentException('Este lançamento já está conciliado.');
        }

        if ($lancamento->ehCredito() !== ($item instanceof ReceivableInstallment)) {
            throw new InvalidArgumentException('Crédito concilia com parcela a receber; débito, com conta a pagar.');
        }

        DB::transaction(function () use ($lancamento, $item): void {
            $lancamento->conciliavel()->associate($item);
            $lancamento->conciliado_em = now();
            $lancamento->save();

            // Baixa com a data do banco: é quando o dinheiro de fato entrou/saiu.
            if ($item instanceof ReceivableInstallment) {
                $item->registrarPagamento($lancamento->data, abs((float) $lancamento->valor));
            } else {
                $item->marcarComoPaga($lancamento->data);
            }
        });
    }
}
