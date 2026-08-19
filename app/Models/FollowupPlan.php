<?php

namespace App\Models;

use App\Enums\FollowupGatilho;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property FollowupGatilho $gatilho
 * @property ?array<string, mixed> $gatilho_config
 * @property bool $ativo
 */
class FollowupPlan extends Model
{
    protected $fillable = [
        'doctor_id',
        'nome',
        'descricao',
        'ativo',
        'gatilho',
        'gatilho_config',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'gatilho' => FollowupGatilho::class,
            'gatilho_config' => 'array',
        ];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return HasMany<FollowupStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FollowupStep::class, 'plan_id')->orderBy('ordem');
    }

    /** @return HasMany<FollowupRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(FollowupRun::class, 'plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
