<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Lançamento do extrato. Valor positivo = crédito, negativo = débito.
 *
 * @property CarbonInterface $data
 * @property CarbonInterface|null $conciliado_em
 */
class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id',
        'data',
        'descricao',
        'valor',
        'fitid',
        'conciliavel_type',
        'conciliavel_id',
        'conciliado_em',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'conciliado_em' => 'datetime',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function conciliavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function ehCredito(): bool
    {
        return (float) $this->valor > 0;
    }

    public function estaConciliado(): bool
    {
        return $this->conciliado_em !== null;
    }

    /**
     * @param  Builder<BankTransaction>  $query
     */
    public function scopeNaoConciliados(Builder $query): void
    {
        $query->whereNull('conciliado_em');
    }
}
