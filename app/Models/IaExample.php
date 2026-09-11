<?php

namespace App\Models;

use App\Enums\IaIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exemplo aprovado pela equipe. Volta para o prompt da agente como few-shot —
 * é o que faz o painel ser treinamento e não só censura.
 */
class IaExample extends Model
{
    protected $fillable = [
        'doctor_id', 'intent', 'canal', 'pergunta', 'resposta',
        'resposta_rejeitada', 'approval_id', 'prioridade', 'ativo', 'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'intent' => IaIntent::class,
            'ativo' => 'boolean',
            'prioridade' => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(IaApproval::class, 'approval_id');
    }
}
