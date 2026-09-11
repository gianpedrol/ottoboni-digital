<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versão publicada das instruções da agente. Nunca é editada: cada publicação
 * cria uma versão nova, com autor, motivo e aceite de responsabilidade.
 * Rollback = republicar uma versão antiga.
 *
 * @property int $id
 * @property int $doctor_id
 * @property int $versao
 * @property array<string, string> $blocos
 * @property bool $ativo
 * @property bool $aceite_responsabilidade
 * @property ?string $motivo
 */
class IaPromptVersion extends Model
{
    /** Blocos que o humano pode editar no painel, na ordem em que entram no prompt. */
    public const BLOCOS = [
        'PERSONA' => 'Quem a agente é',
        'ESCRITA' => 'Como ela escreve',
        'BASE' => 'Informações da clínica',
        'MODOS' => 'Como agir em cada caminho',
        'PROCEDIMENTOS' => 'Programas e procedimentos',
        'ATENCAO' => 'Pontos de atenção',
        'HANDOFF_MSG' => 'Mensagem ao passar para a equipe',
        'COMENTARIO_INSTRUCOES' => 'Comentários: como responder no Direct quem comentou num post',
    ];

    /**
     * Cada canal tem o seu prompt. O Direct usa a conversa completa; quem
     * comentou num post recebe uma resposta curta no Direct, com instruções
     * próprias e só os cards como fonte de fatos.
     */
    public const BLOCOS_POR_CANAL = [
        'direct' => ['PERSONA', 'ESCRITA', 'BASE', 'MODOS', 'PROCEDIMENTOS', 'ATENCAO', 'HANDOFF_MSG'],
        'comentario' => ['COMENTARIO_INSTRUCOES', 'HANDOFF_MSG'],
    ];

    /**
     * @return array<string, string> chave => rótulo, só os blocos do canal
     */
    public static function blocosDoCanal(string $canal): array
    {
        $chaves = self::BLOCOS_POR_CANAL[$canal] ?? self::BLOCOS_POR_CANAL['direct'];

        return array_intersect_key(self::BLOCOS, array_flip($chaves));
    }

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

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
