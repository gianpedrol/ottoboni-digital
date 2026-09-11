<?php

namespace App\Support;

use App\Enums\EtapaCanonica;

/**
 * Traduz status_id do Kommo <-> etapa canônica, por pipeline.
 * Fonte: config/etapas.php. Nunca compara nome de etapa.
 */
class MapaDeEtapas
{
    public static function canonica(int $pipelineId, int $statusId): ?EtapaCanonica
    {
        if ($statusId === (int) config('kommo.status_ganho')) {
            return EtapaCanonica::Contratado;
        }

        if ($statusId === (int) config('kommo.status_perdido')) {
            return EtapaCanonica::Perdido;
        }

        foreach (config("etapas.mapa.{$pipelineId}", []) as $etapa => $ids) {
            if (in_array($statusId, $ids, true)) {
                return EtapaCanonica::from($etapa);
            }
        }

        return null;
    }

    /**
     * @return array<int>
     */
    public static function statusIds(int $pipelineId, EtapaCanonica $etapa): array
    {
        return match ($etapa) {
            EtapaCanonica::Contratado => [(int) config('kommo.status_ganho')],
            EtapaCanonica::Perdido => [(int) config('kommo.status_perdido')],
            default => config("etapas.mapa.{$pipelineId}.{$etapa->value}", []),
        };
    }

    /**
     * Status de destino quando uma automação move o lead para a etapa.
     */
    public static function destino(int $pipelineId, EtapaCanonica $etapa): ?int
    {
        return self::statusIds($pipelineId, $etapa)[0] ?? null;
    }

    /**
     * @return array<int>
     */
    public static function pipelinesMapeados(): array
    {
        return array_map(intval(...), array_keys(config('etapas.mapa', [])));
    }
}
