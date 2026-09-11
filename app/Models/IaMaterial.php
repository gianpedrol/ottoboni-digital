<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Material que a agente pode mandar junto com a resposta (imagem dos
 * programas, e-book, vídeo da Dra.). No prompt ela vê o código e o "quando
 * usar"; na saída devolve os códigos que quer anexar; o humano confere na
 * fila; o n8n envia.
 *
 * @property int $id
 * @property int $doctor_id
 * @property string $codigo
 * @property string $nome
 * @property string $tipo
 * @property ?string $arquivo
 * @property ?string $url
 * @property string $token
 * @property ?string $quando_usar
 * @property bool $ativo
 * @property int $ordem
 */
class IaMaterial extends Model
{
    public const TIPOS = [
        'imagem' => 'Imagem (vai como anexo no Direct)',
        'documento' => 'Documento / PDF (vai como link)',
        'video' => 'Vídeo (link)',
        'link' => 'Página ou link',
    ];

    /** Tipos que vivem como arquivo no painel; os outros são só URL. */
    public const COM_ARQUIVO = ['imagem', 'documento'];

    protected $fillable = [
        'doctor_id', 'codigo', 'nome', 'tipo', 'arquivo', 'url',
        'quando_usar', 'ativo', 'ordem', 'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IaMaterial $m): void {
            if (blank($m->token)) {
                $m->token = Str::random(40);
            }

            $m->codigo = self::normalizarCodigo($m->codigo ?: $m->nome);
        });

        static::updating(function (IaMaterial $m): void {
            $m->codigo = self::normalizarCodigo($m->codigo ?: $m->nome);
        });
    }

    public static function normalizarCodigo(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(60, '')->toString();
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Endereço que o Instagram vai buscar. Arquivo do painel sai pela rota
     * pública com token (não depende de storage:link na hospedagem).
     */
    public function urlPublica(): ?string
    {
        if (in_array($this->tipo, self::COM_ARQUIVO, true) && filled($this->arquivo)) {
            return route('ia.material', ['token' => $this->token, 'nome' => basename((string) $this->arquivo)]);
        }

        return filled($this->url) ? $this->url : null;
    }

    /**
     * Como o material aparece no prompt e no payload para o n8n.
     *
     * @return array{codigo: string, nome: string, tipo: string, url: ?string, quando_usar: ?string}
     */
    public function paraAgente(): array
    {
        return [
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'url' => $this->urlPublica(),
            'quando_usar' => $this->quando_usar,
        ];
    }
}
