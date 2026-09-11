<?php

namespace App\Services\Automacoes;

/**
 * Resultado passo a passo de uma regra contra um lead: pipeline,
 * anti-loop, gatilho, condições e — só se tudo casou — as ações.
 */
final readonly class ResultadoAvaliacao
{
    /**
     * @param  array<int, ResultadoCondicao>  $condicoes
     * @param  array<int, AcaoSimulada>  $acoes
     */
    public function __construct(
        public bool $pipelineOk,
        public string $pipelineMotivo,
        public bool $ignoradoPorLoop,
        public bool $gatilhoCasou,
        public string $gatilhoMotivo,
        public array $condicoes,
        public array $acoes,
        public ?EventoSimulado $evento,
    ) {}

    public function condicoesOk(): bool
    {
        foreach ($this->condicoes as $condicao) {
            if (! $condicao->ok) {
                return false;
            }
        }

        return true;
    }

    public function casou(): bool
    {
        return $this->pipelineOk && ! $this->ignoradoPorLoop && $this->gatilhoCasou && $this->condicoesOk();
    }

    /**
     * Resumo de uma linha — vira o "motivo" da execução.
     */
    public function motivo(): string
    {
        if (! $this->pipelineOk) {
            return $this->pipelineMotivo;
        }

        if ($this->ignoradoPorLoop) {
            return MotorDeAutomacoes::MOTIVO_ANTI_LOOP;
        }

        if (! $this->gatilhoCasou) {
            return "Gatilho não casou: {$this->gatilhoMotivo}";
        }

        foreach ($this->condicoes as $condicao) {
            if (! $condicao->ok) {
                return "Condição não atendida: {$condicao->motivo}";
            }
        }

        $aplicaveis = count(array_filter($this->acoes, fn (AcaoSimulada $a): bool => $a->aplicavel));

        return $aplicaveis === 0
            ? 'Regra casou, mas nenhuma ação é necessária (o lead já está como a regra deixaria).'
            : "Regra casou: {$aplicaveis} " . ($aplicaveis === 1 ? 'ação' : 'ações') . ' a executar.';
    }

    /**
     * @return array<int, AcaoSimulada>
     */
    public function acoesAplicaveis(): array
    {
        return array_values(array_filter($this->acoes, fn (AcaoSimulada $a): bool => $a->aplicavel));
    }

    /**
     * Formato serializável (propriedade Livewire e coluna json da execução).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'casou' => $this->casou(),
            'motivo' => $this->motivo(),
            'pipeline' => ['ok' => $this->pipelineOk, 'motivo' => $this->pipelineMotivo],
            'anti_loop' => [
                'ignorado' => $this->ignoradoPorLoop,
                'motivo' => $this->ignoradoPorLoop ? MotorDeAutomacoes::MOTIVO_ANTI_LOOP : null,
            ],
            'gatilho' => ['ok' => $this->gatilhoCasou, 'motivo' => $this->gatilhoMotivo],
            'condicoes' => array_map(fn (ResultadoCondicao $c): array => $c->toArray(), $this->condicoes),
            'acoes' => array_map(fn (AcaoSimulada $a): array => $a->toArray(), $this->acoes),
            'evento' => $this->evento?->toArray()
                ?? ['tipo' => 'estado', 'descricao' => 'Sem evento: avaliado o estado atual do lead'],
        ];
    }
}
