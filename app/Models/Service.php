<?php

namespace App\Models;

use App\Enums\TipoServico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item da tabela de preços (consulta, retorno, procedimento, cirurgia).
 *
 * O tipo fica como string no model (outros módulos leem direto);
 * rótulo e cor vêm de TipoServico.
 */
class Service extends Model
{
    protected $fillable = [
        'doctor_id',
        'nome',
        'tipo',
        'valor',
        'duracao_min',
        'repasse_pct',
        'ativo',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'duracao_min' => 'integer',
            'repasse_pct' => 'decimal:2',
            'ativo' => 'boolean',
            'demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return HasMany<Receivable, $this>
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function tipoEnum(): ?TipoServico
    {
        return TipoServico::tryFrom((string) $this->tipo);
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeAtivos(Builder $query): void
    {
        $query->where('ativo', true);
    }
}
