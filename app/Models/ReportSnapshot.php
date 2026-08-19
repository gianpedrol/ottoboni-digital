<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $filtros
 * @property ?array<string, mixed> $dados
 * @property ?int $gerado_por
 */
class ReportSnapshot extends Model
{
    protected $fillable = [
        'tipo',
        'status',
        'filtros',
        'dados',
        'erro',
        'gerado_em',
        'gerado_por',
    ];

    protected function casts(): array
    {
        return [
            'filtros' => 'array',
            'dados' => 'array',
            'gerado_em' => 'datetime',
        ];
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por');
    }
}
