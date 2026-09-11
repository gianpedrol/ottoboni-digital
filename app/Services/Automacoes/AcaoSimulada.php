<?php

namespace App\Services\Automacoes;

/**
 * Uma ação que o motor faria, com a chamada exata que iria para o Kommo.
 * metodo "PAINEL" = ação interna do painel (régua da Fase 2, Financeiro).
 */
final readonly class AcaoSimulada
{
    /**
     * @param  array<int|string, mixed>|null  $payload
     */
    public function __construct(
        public string $tipo,
        public string $descricao,
        public bool $aplicavel = true,
        public ?string $metodo = null,
        public ?string $endpoint = null,
        public ?array $payload = null,
        public ?string $observacao = null,
    ) {}

    public static function ignorada(string $tipo, string $descricao, string $motivo): self
    {
        return new self($tipo, $descricao, aplicavel: false, observacao: $motivo);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'descricao' => $this->descricao,
            'aplicavel' => $this->aplicavel,
            'metodo' => $this->metodo,
            'endpoint' => $this->endpoint,
            'payload' => $this->payload,
            'observacao' => $this->observacao,
        ];
    }
}
