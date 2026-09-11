<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra inegociável. Vai sempre no fim do system prompt, prevalecendo sobre
 * qualquer bloco editável — o painel exibe, mas não oferece campo de edição.
 * doctor_id nulo = vale para as duas agentes.
 */
class IaGuardrail extends Model
{
    protected $fillable = ['doctor_id', 'ordem', 'regra', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return array<int, string> */
    public static function paraAgente(int $doctorId): array
    {
        return static::query()
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $doctorId))
            ->orderBy('ordem')
            ->pluck('regra')
            ->all();
    }
}
