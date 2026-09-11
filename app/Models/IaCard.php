<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Card da base de conhecimento. Quando um humano responde um item que caiu
 * como "não sei", a resposta nasce aqui — é assim que a agente não esquece.
 */
class IaCard extends Model
{
    protected $fillable = [
        'doctor_id', 'pergunta', 'resposta', 'tags', 'status',
        'origem', 'approval_id', 'ativo', 'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
