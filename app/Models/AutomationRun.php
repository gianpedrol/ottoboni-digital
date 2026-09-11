<?php

namespace App\Models;

use App\Enums\StatusExecucaoAutomacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Execução (ou registro do que seria executado) de uma regra de automação.
 *
 * @property int $kommo_lead_id
 * @property ?string $lead_nome
 * @property ?int $pipeline_id
 * @property ?array<string, mixed> $evento
 * @property ?array<int, array<string, mixed>> $acoes
 * @property StatusExecucaoAutomacao $status
 * @property ?string $motivo
 * @property bool $demo
 */
class AutomationRun extends Model
{
    protected $fillable = [
        'automation_rule_id',
        'kommo_lead_id',
        'lead_nome',
        'pipeline_id',
        'evento',
        'acoes',
        'status',
        'motivo',
        'demo',
    ];

    protected function casts(): array
    {
        return [
            'kommo_lead_id' => 'integer',
            'pipeline_id' => 'integer',
            'evento' => 'array',
            'acoes' => 'array',
            'status' => StatusExecucaoAutomacao::class,
            'demo' => 'boolean',
        ];
    }

    /** @return BelongsTo<AutomationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
