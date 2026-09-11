<?php

namespace App\Services\Automacoes;

final readonly class ResultadoCondicao
{
    public function __construct(
        public string $descricao,
        public bool $ok,
        public string $motivo,
    ) {}

    /**
     * @return array{descricao: string, ok: bool, motivo: string}
     */
    public function toArray(): array
    {
        return [
            'descricao' => $this->descricao,
            'ok' => $this->ok,
            'motivo' => $this->motivo,
        ];
    }
}
