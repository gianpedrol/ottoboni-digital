<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'apelido',
        'banco',
        'agencia',
        'conta',
        'saldo_inicial',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function saldoAtual(): float
    {
        return (float) $this->saldo_inicial + (float) $this->transactions()->sum('valor');
    }

    public function rotulo(): string
    {
        return "{$this->apelido} ({$this->banco})";
    }
}
