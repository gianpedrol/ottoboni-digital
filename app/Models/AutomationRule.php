<?php

namespace App\Models;

use App\Enums\ModoAutomacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regra de automação do Kommo: gatilho + condições + ações.
 * Formato dos JSONs documentado em App\Services\Automacoes\MotorDeAutomacoes.
 *
 * @property ?string $codigo
 * @property string $nome
 * @property array<string, mixed> $gatilho
 * @property ?array<int, array<string, mixed>> $condicoes
 * @property array<int, array<string, mixed>> $acoes
 * @property array<int, int|string> $pipeline_ids
 * @property ModoAutomacao $modo
 * @property bool $demo
 */
class AutomationRule extends Model
{
    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'gatilho',
        'condicoes',
        'acoes',
        'pipeline_ids',
        'modo',
        'ordem',
        'demo',
    ];

    protected $attributes = [
        'modo' => 'so_registrar',
    ];

    protected function casts(): array
    {
        return [
            'gatilho' => 'array',
            'condicoes' => 'array',
            'acoes' => 'array',
            'pipeline_ids' => 'array',
            'modo' => ModoAutomacao::class,
            'ordem' => 'integer',
            'demo' => 'boolean',
        ];
    }

    /** @return HasMany<AutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function rotulo(): string
    {
        return $this->codigo ? "{$this->codigo} · {$this->nome}" : $this->nome;
    }
}
