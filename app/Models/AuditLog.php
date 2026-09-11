<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'acao',
        'contexto',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'contexto' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra uma ação. Por padrão usa o usuário logado; passe $userId
     * quando o autor vier de outro lugar que não a sessão. O contexto NUNCA
     * deve conter PII do paciente — só filtros, ids e nomes de tela.
     *
     * @param  array<string, mixed>  $contexto
     */
    public static function registrar(string $acao, array $contexto = [], ?int $userId = null): void
    {
        static::query()->create([
            // O autor pode ser informado direto: quem aprova na fila de IA
            // chega pelo serviço, e nem sempre há sessão web (fila, console).
            'user_id' => $userId ?? Auth::id(),
            'acao' => $acao,
            'contexto' => $contexto,
            'created_at' => now(),
        ]);
    }
}
