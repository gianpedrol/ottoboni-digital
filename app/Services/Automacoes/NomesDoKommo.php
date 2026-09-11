<?php

namespace App\Services\Automacoes;

use App\Services\Kommo\DTO\PipelineData;
use App\Support\MapaDeEtapas;
use Illuminate\Support\Collection;

/**
 * Nomes de pipeline e de etapa para as descrições das ações.
 *
 * Os nomes de referência foram lidos da conta em 10/09/2026 (escopo,
 * seção 1) e servem para o protótipo funcionar offline. Quando o Kommo
 * responde, os nomes reais sobrepõem estes. A lógica nunca usa nome:
 * só o status_id.
 */
final class NomesDoKommo
{
    /** @var array<int, string> */
    private const PIPELINES = [
        6441483 => 'Dr Eduardo',
        9219740 => 'Dra Vanessa Ottoboni',
        11380984 => 'Fluxo cirurgias',
        8795248 => 'Dra Jéssica Hubner',
        6456383 => 'Dr Paulo',
        6367607 => 'Dermatologia e Estética - GERAL',
        6366267 => 'Funil de vendas',
    ];

    /** @var array<int, string> */
    private const STATUS = [
        // Dr Eduardo
        54917471 => 'Etapa de leads de entrada',
        54917475 => 'EM ATENDIMENTO',
        79620384 => 'INSTAGRAM',
        84709188 => 'FOLLOW 24 HRS',
        84709192 => 'FOLLOW UP 5 DIAS',
        84710072 => 'FOLLOW 1 / Ñ AGEND',
        69609380 => 'NÃO AGENDOU',
        75912472 => 'AGUARDANDO SINAL',
        85174916 => 'agendou e não pagou sinal',
        54917479 => 'AGENDOU CONSULTA/PROCEDIMENTO',
        56708683 => 'realizou consulta',
        54917483 => 'EM Negociação',
        69228540 => 'AGENDOU CIRURGIA',
        // Dra Vanessa
        71591204 => 'Etapa de leads de entrada',
        71591208 => 'EM ATENDIMENTO',
        108897619 => 'INSTAGRAM',
        110706659 => 'FU1 · SEM RESPOSTA',
        110706663 => 'FU2 · AGEND. NÃO CONCLUÍDO',
        110706667 => 'FU3 · ORÇAMENTO ENVIADO',
        71591216 => 'NÃO AGENDOU / FOLLOW UP',
        71591324 => '1 AGENDOU CONSULTA',
        71591328 => '1 AGEND. CONS. ONLINE',
        73758392 => 'REALIZOU 1ª CONSULTA',
        71591332 => 'CONSULTORIA',
        71591336 => 'AGENDOU PROCEDIMENTO',
        // Iguais em todos os pipelines
        142 => 'Contratado (Venda ganha)',
        143 => 'Venda perdida',
    ];

    /**
     * @param  array<int, string>  $status  sobrepõe os nomes de referência
     * @param  array<int, string>  $pipelines
     */
    public function __construct(
        private readonly array $status = [],
        private readonly array $pipelines = [],
    ) {}

    /**
     * @param  Collection<int, PipelineData>  $pipelines
     */
    public static function comPipelines(Collection $pipelines): self
    {
        $status = [];
        $nomes = [];

        foreach ($pipelines as $pipeline) {
            $nomes[$pipeline->id] = $pipeline->name;

            foreach ($pipeline->statuses as $s) {
                // 142/143 se repetem em todo pipeline; o rótulo fixo é mais claro.
                if (! in_array($s->id, [142, 143], true)) {
                    $status[$s->id] = $s->name;
                }
            }
        }

        return new self($status, $nomes);
    }

    public function pipeline(int $pipelineId): string
    {
        return $this->pipelines[$pipelineId] ?? self::PIPELINES[$pipelineId] ?? "pipeline {$pipelineId}";
    }

    public function status(int $pipelineId, int $statusId): string
    {
        $nome = $this->status[$statusId] ?? self::STATUS[$statusId] ?? null;

        if ($nome !== null) {
            return $nome;
        }

        $etapa = MapaDeEtapas::canonica($pipelineId, $statusId);

        return $etapa !== null ? $etapa->getLabel() : "etapa {$statusId}";
    }
}
