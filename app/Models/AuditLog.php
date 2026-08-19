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
     * Registra uma ação do usuário logado. O contexto NUNCA deve conter
     * PII do paciente — só filtros, ids e nomes de tela.
     *
     * @param  array<string, mixed>  $contexto
     */
    public static function registrar(string $acao, array $contexto = []): void
    {
        static::query()->create([
            'user_id' => Auth::id(),
            'acao' => $acao,
            'contexto' => $contexto,
            'created_at' => now(),
        ]);
    }
}
