<?php

namespace App\Models;

use App\Enums\FollowupCanal;
use App\Enums\FollowupModo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $ordem
 * @property int $offset_horas
 * @property FollowupCanal $canal
 * @property FollowupModo $modo
 * @property ?string $texto
 * @property ?string $prompt_ia
 * @property ?int $kommo_bot_id
 * @property bool $ativo
 */
class FollowupStep extends Model
{
    protected $fillable = [
        'plan_id',
        'ordem',
        'offset_horas',
        'canal',
        'modo',
        'texto',
        'prompt_ia',
        'kommo_bot_id',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'offset_horas' => 'integer',
            'canal' => FollowupCanal::class,
            'modo' => FollowupModo::class,
            'kommo_bot_id' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsTo<FollowupPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(FollowupPlan::class, 'plan_id');
    }
}
