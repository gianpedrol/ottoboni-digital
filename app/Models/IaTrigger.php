<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gatilho de comentário (o que hoje vive na tabela luna_gatilhos do Supabase).
 */
class IaTrigger extends Model
{
    protected $fillable = ['doctor_id', 'termo', 'tipo', 'ordem', 'ativo'];

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
}
