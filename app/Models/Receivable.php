<?php

namespace App\Models;

use App\Enums\StatusFinanceiro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta a receber; o dinheiro de fato está nas parcelas.
 */
class Receivable extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'service_id',
        'descricao',
        'valor_total',
        'forma_pagamento',
        'origem',
        'kommo_lead_id',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'kommo_lead_id' => 'integer',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<ReceivableInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(ReceivableInstallment::class)->orderBy('numero');
    }

    /**
     * Situação do recebível a partir das parcelas: atrasado se alguma
     * parcela está vencida, pago se todas foram pagas, senão em aberto.
     */
    public function situacao(): StatusFinanceiro
    {
        $parcelas = $this->installments->reject(
            fn (ReceivableInstallment $p): bool => $p->status === StatusFinanceiro::Cancelado->value,
        );

        if ($parcelas->isEmpty()) {
            return StatusFinanceiro::Aberto;
        }

        $situacoes = $parcelas->map(fn (ReceivableInstallment $p): StatusFinanceiro => $p->situacao());

        if ($situacoes->contains(StatusFinanceiro::Atrasado)) {
            return StatusFinanceiro::Atrasado;
        }

        return $situacoes->every(fn (StatusFinanceiro $s): bool => $s === StatusFinanceiro::Pago)
            ? StatusFinanceiro::Pago
            : StatusFinanceiro::Aberto;
    }

    public function valorRecebido(): float
    {
        return (float) $this->installments
            ->where('status', StatusFinanceiro::Pago->value)
            ->sum(fn (ReceivableInstallment $p): float => (float) ($p->valor_pago ?? $p->valor));
    }

    public function valorEmAberto(): float
    {
        return (float) $this->installments
            ->whereIn('status', [StatusFinanceiro::Aberto->value, StatusFinanceiro::Atrasado->value])
            ->sum(fn (ReceivableInstallment $p): float => (float) $p->valor);
    }

    /**
     * Filtro pela situação calculada (aberto, atrasado, pago).
     *
     * @param  Builder<Receivable>  $query
     */
    public function scopeComSituacao(Builder $query, string $situacao): void
    {
        match ($situacao) {
            StatusFinanceiro::Atrasado->value => $query->whereHas(
                'installments',
                fn (Builder $q) => $q->scopes(['atrasadas']),
            ),
            StatusFinanceiro::Pago->value => $query
                ->whereHas('installments')
                ->whereDoesntHave('installments', fn (Builder $q) => $q->scopes(['emAberto'])),
            StatusFinanceiro::Aberto->value => $query
                ->whereHas('installments', fn (Builder $q) => $q->scopes(['emAberto']))
                ->whereDoesntHave('installments', fn (Builder $q) => $q->scopes(['atrasadas'])),
            default => null,
        };
    }
}
