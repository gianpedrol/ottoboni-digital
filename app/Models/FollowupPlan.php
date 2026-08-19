<?php

namespace App\Models;

use App\Enums\FollowupGatilho;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FollowupStep::class, 'plan_id')->orderBy('ordem');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(FollowupRun::class, 'plan_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
