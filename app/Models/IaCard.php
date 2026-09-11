<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Card da base de conhecimento. Quando um humano responde um item que caiu
 * como "não sei", a resposta nasce aqui — é assim que a agente não esquece.
 *
 * Status:
 *  - validado: a agente usa
 *  - revisar:  a agente usa, mas alguém marcou para a clínica conferir
 *  - pendente: a agente NUNCA responde sobre isso; escala para a equipe
 *
 * @property int $id
 * @property int $doctor_id
 * @property ?string $codigo
 * @property ?string $modulo
 * @property ?string $categoria
 * @property string $pergunta
 * @property ?array<int, string> $perguntas_equivalentes
 * @property string $resposta
 * @property ?string $resposta_detalhada
 * @property ?array<int, string> $tags
 * @property string $status
 * @property string $origem
 * @property bool $ativo
 */
class IaCard extends Model
{
    public const STATUS = [
        'validado' => 'Validado (a agente usa)',
        'revisar' => 'Revisar (usa, mas a clínica precisa conferir)',
        'pendente' => 'Pendente (a agente não responde e escala)',
    ];

    protected $fillable = [
        'doctor_id', 'codigo', 'modulo', 'categoria',
        'pergunta', 'perguntas_equivalentes', 'resposta', 'resposta_detalhada',
        'tags', 'status', 'origem', 'approval_id', 'ativo', 'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'perguntas_equivalentes' => 'array',
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * A agente pode usar este card como fonte de fatos?
     */
    public function usavel(): bool
    {
        return $this->ativo && in_array($this->status, ['validado', 'revisar'], true);
    }

    /**
     * Identificador estável que vai no prompt (o mesmo que o n8n já usa hoje).
     */
    public function rotulo(): string
    {
        return $this->codigo ?? (string) $this->id;
    }

    /**
     * Todas as formas de perguntar que este card cobre.
     *
     * @return array<int, string>
     */
    public function perguntas(): array
    {
        $lista = $this->perguntas_equivalentes ?? [];

        if ($lista === [] && filled($this->pergunta)) {
            $lista = [$this->pergunta];
        }

        return array_values(array_filter(array_map('strval', $lista), fn (string $p): bool => $p !== ''));
    }
}
