<?php

namespace App\Models;

use App\Enums\CategoriaDespesa;
use App\Enums\StatusFinanceiro;
use App\Support\Financeiro;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property CarbonInterface $vencimento
 * @property CarbonInterface|null $pago_em
 */
class Payable extends Model
{
    protected $fillable = [
        'fornecedor',
        'categoria',
        'descricao',
        'valor',
        'vencimento',
        'pago_em',
        'recorrente',
        'status',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'pago_em' => 'date',
            'recorrente' => 'boolean',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return MorphMany<BankTransaction, $this>
     */
    public function bankTransactions(): MorphMany
    {
        return $this->morphMany(BankTransaction::class, 'conciliavel');
    }

    public function situacao(): StatusFinanceiro
    {
        $status = StatusFinanceiro::tryFrom((string) $this->status) ?? StatusFinanceiro::Aberto;

        if ($status === StatusFinanceiro::Aberto && $this->vencimento->lt(Financeiro::hoje())) {
            return StatusFinanceiro::Atrasado;
        }

        return $status;
    }

    public function marcarComoPaga(CarbonInterface|string $data): void
    {
        $this->update([
            'pago_em' => $data,
            'status' => StatusFinanceiro::Pago->value,
        ]);
    }

    public function rotulo(): string
    {
        $categoria = CategoriaDespesa::tryFrom((string) $this->categoria)?->getLabel() ?? $this->categoria;

        return "{$this->fornecedor} · {$categoria} · venc. {$this->vencimento->format('d/m/Y')} · "
            . Financeiro::brl($this->valor);
    }

    /**
     * @param  Builder<Payable>  $query
     */
    public function scopeEmAberto(Builder $query): void
    {
        $query->whereIn('status', [StatusFinanceiro::Aberto->value, StatusFinanceiro::Atrasado->value]);
    }

    /**
     * @param  Builder<Payable>  $query
     */
    public function scopeAtrasadas(Builder $query): void
    {
        $query->emAberto()->whereDate('vencimento', '<', Financeiro::hoje()->toDateString());
    }

    /**
     * @param  Builder<Payable>  $query
     */
    public function scopePagas(Builder $query): void
    {
        $query->where('status', StatusFinanceiro::Pago->value);
    }

    /**
     * Filtro pela situação calculada (aberto, atrasado, pago).
     *
     * @param  Builder<Payable>  $query
     */
    public function scopeComSituacao(Builder $query, string $situacao): void
    {
        $hoje = Financeiro::hoje()->toDateString();

        match ($situacao) {
            StatusFinanceiro::Atrasado->value => $query->atrasadas(),
            StatusFinanceiro::Pago->value => $query->pagas(),
            StatusFinanceiro::Aberto->value => $query->emAberto()->whereDate('vencimento', '>=', $hoje),
            default => null,
        };
    }
}
