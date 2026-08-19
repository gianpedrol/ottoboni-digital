<?php

namespace App\Models;

use App\Enums\FollowupCanal;
use App\Enums\FollowupRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $lead_id_kommo
 * @property FollowupRunStatus $status
 * @property ?FollowupCanal $canal_usado
 * @property \Illuminate\Support\Carbon $agendado_para
 * @property ?\Illuminate\Support\Carbon $executado_em
 */
class FollowupRun extends Model
{
    protected $fillable = [
        'plan_id',
        'step_id',
        'lead_id_kommo',
        'doctor_id',
        'status',
        'agendado_para',
        'executado_em',
        'canal_usado',
        'motivo_cancelamento',
    ];

    protected function casts(): array
    {
        return [
            'lead_id_kommo' => 'integer',
            'status' => FollowupRunStatus::class,
            'agendado_para' => 'datetime',
            'executado_em' => 'datetime',
            'canal_usado' => FollowupCanal::class,
        ];
    }

    /** @return BelongsTo<FollowupPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(FollowupPlan::class, 'plan_id');
    }

    /** @return BelongsTo<FollowupStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(FollowupStep::class, 'step_id');
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return HasMany<FollowupMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(FollowupMessage::class, 'run_id');
    }
}
