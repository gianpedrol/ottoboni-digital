<?php

namespace App\Services\Ia\DTO;

use App\Enums\IaMotivoFila;

/**
 * O veredito do portão para uma interação. Se enviarDireto é false, nada sai
 * para o Instagram até um humano aprovar no painel.
 */
final class DecisaoDoPortao
{
    /**
     * @param  array<string, mixed>  $detalhes
     */
    public function __construct(
        public readonly bool $enviarDireto,
        public readonly IaMotivoFila $motivo,
        public readonly string $modelo,
        public readonly int $timeoutMin = 30,
        public readonly bool $dmEspera = false,
        public readonly ?string $dmEsperaTexto = null,
        public readonly array $detalhes = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enviar_direto' => $this->enviarDireto,
            'motivo' => $this->motivo->value,
            'motivo_rotulo' => $this->motivo->getLabel(),
            'modelo' => $this->modelo,
            'timeout_min' => $this->timeoutMin,
            'dm_espera' => $this->dmEspera,
            'dm_espera_texto' => $this->dmEsperaTexto,
            'detalhes' => $this->detalhes,
        ];
    }
}
