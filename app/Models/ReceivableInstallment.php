<?php

namespace App\Models;

use App\Enums\StatusFinanceiro;
use App\Support\Financeiro;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property CarbonInterface $vencimento
 * @property CarbonInterface|null $pago_em
 */
class ReceivableInstallment extends Model
{
    protected $fillable = [
        'receivable_id',
        'numero',
        'valor',
        'vencimento',
        'pago_em',
        'valor_pago',
        'status',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'pago_em' => 'date',
            'valor_pago' => 'decimal:2',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Receivable, $this>
     */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
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

    public function registrarPagamento(CarbonInterface|string $data, float $valor): void
    {
        $this->update([
            'pago_em' => $data,
            'valor_pago' => $valor,
            'status' => StatusFinanceiro::Pago->value,
        ]);
    }

    /**
     * Rótulo usado na conciliação: "Parcela 2/5 · Maria · venc. 10/08/2026 · R$ 1.000,00".
     */
    public function rotulo(): string
    {
        $receivable = $this->receivable;
        $total = $receivable->installments()->count();
        $quem = $receivable->patient->nome ?? $receivable->descricao;

        return "Parcela {$this->numero}/{$total} · {$quem} · venc. {$this->vencimento->format('d/m/Y')} · "
            . Financeiro::brl($this->valor);
    }

    /**
     * @param  Builder<ReceivableInstallment>  $query
     */
    public function scopeEmAberto(Builder $query): void
    {
        $query->whereIn('status', [StatusFinanceiro::Aberto->value, StatusFinanceiro::Atrasado->value]);
    }

    /**
     * @param  Builder<ReceivableInstallment>  $query
     */
    public function scopeAtrasadas(Builder $query): void
    {
        $query->emAberto()->whereDate('vencimento', '<', Financeiro::hoje()->toDateString());
    }

    /**
     * @param  Builder<ReceivableInstallment>  $query
     */
    public function scopePagas(Builder $query): void
    {
        $query->where('status', StatusFinanceiro::Pago->value);
    }
}
