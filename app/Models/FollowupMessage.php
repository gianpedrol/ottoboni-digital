<?php

namespace App\Models;

use App\Enums\FollowupCanal;
use App\Enums\OrigemTexto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupMessage extends Model
{
    protected $fillable = [
        'run_id',
        'canal',
        'texto_final',
        'origem_texto',
        'resposta_n8n',
        'erro',
    ];

    protected function casts(): array
    {
        return [
            'canal' => FollowupCanal::class,
            'origem_texto' => OrigemTexto::class,
            'resposta_n8n' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FollowupRun::class, 'run_id');
    }
}
