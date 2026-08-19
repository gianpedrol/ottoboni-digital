<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'nome',
        'agente',
        'kommo_pipeline_id',
        'ig_user_id',
        'kommo_bot_id',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'kommo_pipeline_id' => 'integer',
            'kommo_bot_id' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function followupPlans(): HasMany
    {
        return $this->hasMany(FollowupPlan::class);
    }

    public static function byPipeline(int $pipelineId): ?self
    {
        return static::query()->where('kommo_pipeline_id', $pipelineId)->first();
    }
}
