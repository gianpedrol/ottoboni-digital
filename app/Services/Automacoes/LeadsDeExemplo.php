<?php

namespace App\Services\Automacoes;

use App\Enums\EtapaCanonica;
use App\Services\Kommo\DTO\LeadData;
use App\Support\MapaDeEtapas;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Leads fictícios para demonstrar as automações sem token do Kommo.
 * O status_id sai do mapa de etapas, então o exemplo acompanha o
 * config/etapas.php. Nomes e IDs inventados.
 */
final class LeadsDeExemplo
{
    /**
     * @return array<string, array{rotulo: string, pipeline: string, etapa: EtapaCanonica, id: int, nome: string, tags: array<int, string>, campos: array<string, mixed>, extras: array<string, string>, codigos: array<int, string>}>
     */
    private static function definicoes(): array
    {
        $tz = (string) config('painel.timezone');
        $agora = CarbonImmutable::now($tz);

        return [
            'eduardo_atendimento' => [
                'rotulo' => 'IG @rafa.costa · Dr Eduardo · Em atendimento, sem tags',
                'pipeline' => 'duda',
                'etapa' => EtapaCanonica::EmAtendimento,
                'id' => 23807731,
                'nome' => 'IG @rafa.costa',
                'tags' => [],
                'campos' => ['procedimento' => 'Rinoplastia', 'temperatura' => 'Quente', 'origem' => 'Instagram'],
                'extras' => [],
                'codigos' => [],
            ],
            'eduardo_fup1' => [
                'rotulo' => 'IG @marina.alves · Dr Eduardo · FUP 1, tag Contato1, sem consulta marcada',
                'pipeline' => 'duda',
                'etapa' => EtapaCanonica::Fup1,
                'id' => 23810457,
                'nome' => 'IG @marina.alves',
                'tags' => ['Contato1'],
                'campos' => ['procedimento' => 'Rinoplastia', 'temperatura' => 'Morno', 'origem' => 'Instagram'],
                'extras' => [],
                'codigos' => ['A2', 'A1'],
            ],
            'vanessa_fup2' => [
                'rotulo' => 'Juliana Prado · Dra Vanessa · FUP 2, tags Contato2 e botox',
                'pipeline' => 'luna',
                'etapa' => EtapaCanonica::Fup2,
                'id' => 31904288,
                'nome' => 'Juliana Prado',
                'tags' => ['Contato2', 'botox'],
                'campos' => ['procedimento' => 'Harmonização facial', 'temperatura' => 'Morno', 'origem' => 'Indicação'],
                'extras' => [],
                'codigos' => ['A2', 'A1'],
            ],
            'eduardo_agendado' => [
                'rotulo' => 'IG @carla.menezes · Dr Eduardo · Consulta agendada para amanhã 14h',
                'pipeline' => 'duda',
                'etapa' => EtapaCanonica::ConsultaAgendada,
                'id' => 23811902,
                'nome' => 'IG @carla.menezes',
                'tags' => [],
                'campos' => [
                    'procedimento' => 'Mamoplastia',
                    'temperatura' => 'Quente',
                    'origem' => 'Instagram',
                    'proximaConsulta' => $agora->addDay()->setTime(14, 0)->utc(),
                ],
                'extras' => [],
                'codigos' => ['A3', 'A5'],
            ],
            'vanessa_realizou' => [
                'rotulo' => 'Patrícia Lima · Dra Vanessa · Realizou 1ª consulta, sem proposta',
                'pipeline' => 'luna',
                'etapa' => EtapaCanonica::ConsultaRealizada,
                'id' => 31905510,
                'nome' => 'Patrícia Lima',
                'tags' => [],
                'campos' => [
                    'procedimento' => 'Lipo HD',
                    'temperatura' => 'Quente',
                    'origem' => 'Google',
                    'comparecimento' => 'Compareceu',
                    'proximaConsulta' => $agora->subDay()->setTime(10, 0)->utc(),
                ],
                'extras' => [],
                'codigos' => ['A4'],
            ],
            'eduardo_negociacao' => [
                'rotulo' => 'Fernanda Rocha · Dr Eduardo · Em negociação, proposta de R$ 32.000',
                'pipeline' => 'duda',
                'etapa' => EtapaCanonica::Negociacao,
                'id' => 23809377,
                'nome' => 'Fernanda Rocha',
                'tags' => [],
                'campos' => [
                    'procedimento' => 'Mamoplastia',
                    'temperatura' => 'Quente',
                    'origem' => 'Instagram',
                    'comparecimento' => 'Compareceu',
                    'valorProposta' => 32000.0,
                    'valorTotal' => '32.000,00',
                    'cirurgia' => 'Mamoplastia de aumento',
                    'dataCirurgia' => $agora->addDays(35)->format('d/m/Y'),
                    'medicoResponsavel' => 'Dr Eduardo',
                ],
                'extras' => [
                    'hospital' => 'Hospital Unimed Sorocaba',
                    'anestesista' => 'Dr. Ricardo Alves',
                    'protese' => 'Silimed 300 ml',
                ],
                'codigos' => ['A6'],
            ],
            'eduardo_sinal' => [
                'rotulo' => 'IG @bia.santos · Dr Eduardo · Aguardando sinal (pendente)',
                'pipeline' => 'duda',
                'etapa' => EtapaCanonica::AguardandoSinal,
                'id' => 23812640,
                'nome' => 'IG @bia.santos',
                'tags' => ['SINAL'],
                'campos' => [
                    'procedimento' => 'Botox',
                    'temperatura' => 'Quente',
                    'origem' => 'Instagram',
                    'statusSinal' => 'Pendente',
                    'proximaConsulta' => $agora->addDays(5)->setTime(10, 0)->utc(),
                ],
                'extras' => [],
                'codigos' => ['A7'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return array_map(fn (array $d): string => $d['rotulo'], self::definicoes());
    }

    public static function existe(string $chave): bool
    {
        return isset(self::definicoes()[$chave]);
    }

    /**
     * Primeiro exemplo que demonstra a regra (ex.: A6 → Fernanda em negociação).
     */
    public static function sugeridoPara(?string $codigo): string
    {
        foreach (self::definicoes() as $chave => $d) {
            if ($codigo !== null && in_array($codigo, $d['codigos'], true)) {
                return $chave;
            }
        }

        return 'eduardo_fup1';
    }

    /**
     * @return array<int, string>
     */
    public static function paraCodigo(string $codigo): array
    {
        return array_keys(array_filter(
            self::definicoes(),
            fn (array $d): bool => in_array($codigo, $d['codigos'], true),
        ));
    }

    /**
     * Monta o lead. Id, nome e pipeline podem ser trocados (execuções de
     * demonstração); a etapa canônica do exemplo é mantida.
     */
    public static function lead(string $chave, ?int $id = null, ?string $nome = null, ?int $pipelineId = null): LeadData
    {
        $d = self::definicoes()[$chave] ?? throw new InvalidArgumentException("Lead de exemplo desconhecido: {$chave}");

        $padrao = (int) config("kommo.pipelines.{$d['pipeline']}");
        $pipelineId ??= $padrao;
        $statusId = MapaDeEtapas::destino($pipelineId, $d['etapa']);

        if ($statusId === null) {
            $pipelineId = $padrao;
            $statusId = (int) MapaDeEtapas::destino($padrao, $d['etapa']);
        }

        $id ??= $d['id'];
        $nome ??= $d['nome'];

        return new LeadData(...[
            'id' => $id,
            'name' => $nome,
            'pipelineId' => $pipelineId,
            'statusId' => $statusId,
            'responsibleUserId' => null,
            'createdAt' => CarbonImmutable::now()->subDays(6),
            'updatedAt' => CarbonImmutable::now()->subHours(3),
            'closedAt' => null,
            'price' => 0,
            'origem' => null,
            'temperatura' => null,
            'procedimento' => null,
            'urgencia' => null,
            'score' => null,
            'instagram' => str_starts_with($nome, 'IG @') ? substr($nome, 4) : null,
            'contactIds' => [40000000 + ($id % 1000000)],
            'lossReason' => null,
            'tags' => $d['tags'],
            ...$d['campos'],
        ]);
    }

    /**
     * Campos que o LeadData não carrega (Hospital, Anestesista, Prótese).
     *
     * @return array<string, string>
     */
    public static function camposExtras(string $chave): array
    {
        return self::definicoes()[$chave]['extras'] ?? [];
    }
}
