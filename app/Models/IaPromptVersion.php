<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versão publicada das instruções da agente. Nunca é editada: cada publicação
 * cria uma versão nova, com autor, motivo e aceite de responsabilidade.
 * Rollback = republicar uma versão antiga.
 *
 * @property array<string, string> $blocos
 */
class IaPromptVersion extends Model
{
    /** Blocos que o humano pode editar no painel. */
    public const BLOCOS = [
        'PERSONA' => 'Quem a agente é',
        'ESCRITA' => 'Como ela escreve',
        'BASE' => 'Informações da clínica',
        'MODOS' => 'Como agir em cada caminho',
        'PROCEDIMENTOS' => 'Programas e procedimentos',
        'ATENCAO' => 'Pontos de atenção',
        'HANDOFF_MSG' => 'Mensagem ao passar para a equipe',
    ];

    protected $fillable = [
        'doctor_id', 'versao', 'blocos', 'ativo', 'autor_id',
        'motivo', 'aceite_responsabilidade', 'aceite_texto', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'blocos' => 'array',
            'ativo' => 'boolean',
            'aceite_responsabilidade' => 'boolean',
            'versao' => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
