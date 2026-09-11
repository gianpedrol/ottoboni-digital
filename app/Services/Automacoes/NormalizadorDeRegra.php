<?php

namespace App\Services\Automacoes;

/**
 * Deixa os JSONs da regra no formato do motor: só as chaves de cada tipo,
 * números como int, listas sem chave de UUID. Também converte as ações
 * de/para o formato do componente Builder do Filament.
 */
final class NormalizadorDeRegra
{
    /** @var array<string, array<int, string>> */
    private const CHAVES_GATILHO = [
        'etapa' => ['etapas'],
        'campo_alterado' => ['campo', 'valor'],
        'campo_preenchido' => ['campo'],
        'tag_adicionada' => ['tag'],
        'inatividade' => ['horas'],
        'lead_criado' => [],
    ];

    /** @var array<string, array<int, string>> */
    private const CHAVES_CONDICAO = [
        'etapa_atual' => ['etapas'],
        'campo_preenchido' => ['campo'],
        'campo_igual' => ['campo', 'valor'],
        'tem_tag' => ['tag'],
    ];

    /** @var array<string, array<int, string>> */
    private const CHAVES_ACAO = [
        'mover_etapa' => ['etapa'],
        'adicionar_tag' => ['tag'],
        'remover_tags' => ['tags'],
        'preencher_campo' => ['campo', 'valor'],
        'criar_tarefa' => ['texto', 'prazo_horas'],
        'criar_card_pipeline' => ['pipeline', 'etapa', 'copiar_campos'],
        'iniciar_regua' => ['followup_plan_id'],
        'cancelar_reguas' => [],
        'disparar_salesbot' => ['bot_id'],
        'criar_recebivel' => [],
    ];

    private const INTEIROS = ['horas', 'prazo_horas', 'followup_plan_id', 'bot_id'];

    private const LISTAS = ['etapas', 'tags', 'copiar_campos'];

    /**
     * @param  array<string, mixed>  $gatilho
     * @return array<string, mixed>
     */
    public static function gatilho(array $gatilho, bool $comAlternativas = true): array
    {
        $tipo = (string) ($gatilho['tipo'] ?? '');
        $saida = ['tipo' => $tipo, ...self::filtrar($gatilho, self::CHAVES_GATILHO[$tipo] ?? [])];

        if ($comAlternativas) {
            $ou = [];

            foreach (array_values((array) ($gatilho['ou'] ?? [])) as $alternativa) {
                if (is_array($alternativa) && filled($alternativa['tipo'] ?? null)) {
                    $ou[] = self::gatilho($alternativa, false);
                }
            }

            if ($ou !== []) {
                $saida['ou'] = $ou;
            }
        }

        return $saida;
    }

    /**
     * @param  array<int|string, mixed>|null  $condicoes
     * @return array<int, array<string, mixed>>
     */
    public static function condicoes(?array $condicoes): array
    {
        $saida = [];

        foreach (array_values($condicoes ?? []) as $c) {
            if (is_array($c) && filled($c['tipo'] ?? null)) {
                $tipo = (string) $c['tipo'];
                $saida[] = ['tipo' => $tipo, ...self::filtrar($c, self::CHAVES_CONDICAO[$tipo] ?? [])];
            }
        }

        return $saida;
    }

    /**
     * @param  array<int|string, mixed>|null  $acoes
     * @return array<int, array<string, mixed>>
     */
    public static function acoes(?array $acoes): array
    {
        $saida = [];

        foreach (array_values($acoes ?? []) as $a) {
            if (is_array($a) && filled($a['tipo'] ?? null)) {
                $tipo = (string) $a['tipo'];
                $saida[] = ['tipo' => $tipo, ...self::filtrar($a, self::CHAVES_ACAO[$tipo] ?? [])];
            }
        }

        return $saida;
    }

    /**
     * Formato do motor → itens do Builder ({type, data}).
     *
     * @param  array<int, array<string, mixed>>|null  $acoes
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function paraBuilder(?array $acoes): array
    {
        return array_map(function (array $a): array {
            $tipo = (string) ($a['tipo'] ?? '');
            unset($a['tipo']);

            return ['type' => $tipo, 'data' => $a];
        }, self::acoes($acoes));
    }

    /**
     * Itens do Builder → formato do motor.
     *
     * @param  array<int|string, mixed>|null  $blocos
     * @return array<int, array<string, mixed>>
     */
    public static function doBuilder(?array $blocos): array
    {
        $acoes = [];

        foreach (array_values($blocos ?? []) as $bloco) {
            if (is_array($bloco) && isset($bloco['type'])) {
                $acoes[] = ['tipo' => (string) $bloco['type'], ...(array) ($bloco['data'] ?? [])];
            }
        }

        return self::acoes($acoes);
    }

    /**
     * @param  array<int|string, mixed>  $pipelineIds
     * @return array<int, int>
     */
    public static function pipelines(array $pipelineIds): array
    {
        return array_values(array_map(intval(...), array_filter($pipelineIds, filled(...))));
    }

    /**
     * @param  array<int|string, mixed>  $origem
     * @param  array<int, string>  $chaves
     * @return array<string, mixed>
     */
    private static function filtrar(array $origem, array $chaves): array
    {
        $saida = [];

        foreach ($chaves as $chave) {
            $valor = self::escalar($origem[$chave] ?? null);

            if (in_array($chave, self::LISTAS, true)) {
                $lista = array_values(array_filter(array_map(self::escalar(...), (array) $valor), filled(...)));

                if ($lista !== []) {
                    $saida[$chave] = $lista;
                }

                continue;
            }

            if (blank($valor)) {
                continue;
            }

            $saida[$chave] = in_array($chave, self::INTEIROS, true) || ($chave === 'etapa' && is_numeric($valor))
                ? (int) $valor
                : $valor;
        }

        return $saida;
    }

    /**
     * Select com enum pode devolver o case em vez do valor.
     */
    public static function escalar(mixed $valor): mixed
    {
        return $valor instanceof \BackedEnum ? $valor->value : $valor;
    }
}
