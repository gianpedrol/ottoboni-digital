<?php

namespace App\Services\Pacientes;

use App\Enums\KommoSyncStatus;

final readonly class ResultadoSincronizacao
{
    public function __construct(
        public KommoSyncStatus $status,
        public string $titulo,
        public string $mensagem,
    ) {}
}
