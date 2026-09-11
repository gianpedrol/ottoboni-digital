<?php

namespace App\Services\Automacoes;

use App\Enums\EtapaCanonica;

/**
 * Descreve gatilho, condições e ações em português, sem olhar lead
 * nenhum (lista de regras, execuções). A descrição com o lead concreto
 * ("Mover de FOLLOW 24 HRS para …") sai do MotorDeAutomacoes.
 */
final class DescritorDeRegra
{
    /** @var array<string, string> */
    public const TIPOS_GATILHO = [
        'etapa' => 'Entrou numa etapa',
        'campo_alterado' => 'Campo mudou para um valor',
        'campo_preenchido' => 'Campo foi preenchido',
        'tag_adicionada' => 'Tag adicionada',
        'inatividade' => 'Lead parado há X horas',
        'lead_criado' => 'Lead criado',
    ];

    /** @var array<string, string> */
    public const TIPOS_CONDICAO = [
        'etapa_atual' => 'Lead está numa destas etapas',
        'campo_preenchido' => 'Campo está preenchido',
        'campo_igual' => 'Campo é igual a',
        'tem_tag' => 'Lead tem a tag',
    ];

    /** @var array<string, string> */
    public const TIPOS_ACAO = [
        'mover_etapa' => 'Mover de etapa',
        'adicionar_tag' => 'Adicionar tag',
        'remover_tags' => 'Remover tags',
        'preencher_campo' => 'Preencher campo',
        'criar_tarefa' => 'Criar tarefa',
        'criar_card_pipeline' => 'Criar card em outro pipeline',
        'iniciar_regua' => 'Iniciar régua de follow-up',
        'cancelar_reguas' => 'Cancelar follow-ups pendentes',
        'disparar_salesbot' => 'Disparar Salesbot',
        'criar_recebivel' => 'Gerar recebível no Financeiro',
    ];

    /**
     * @param  array<string, mixed>|null  $gatilho
     */
    public static function gatilho(?array $gatilho): string
    {
        if ($gatilho === null || ! isset($gatilho['tipo'])) {
            return '—';
        }

        $partes = [self::umGatilho($gatilho)];

        foreach ($gatilho['ou'] ?? [] as $alternativa) {
            if (is_array($alternativa)) {
                $partes[] = self::umGatilho($alternativa);
            }
        }

        return implode(' · ou · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $g
     */
    public static function umGatilho(array $g): string
    {
        return match ($g['tipo'] ?? null) {
            'etapa' => 'Entrou em ' . self::etapas($g['etapas'] ?? []),
            'campo_alterado' => CamposDoLead::rotulo((string) ($g['campo'] ?? '')) . ' mudou para “' . ($g['valor'] ?? '') . '”',
            'campo_preenchido' => CamposDoLead::rotulo((string) ($g['campo'] ?? '')) . ' foi preenchido',
            'tag_adicionada' => 'Tag ' . ($g['tag'] ?? '') . ' adicionada',
            'inatividade' => 'Lead parado há ' . ($g['horas'] ?? '?') . ' h',
            'lead_criado' => 'Lead criado',
            default => 'Gatilho desconhecido',
        };
    }

    /**
     * @param  array<string, mixed>  $c
     */
    public static function condicao(array $c): string
    {
        return match ($c['tipo'] ?? null) {
            'etapa_atual' => 'Lead está em ' . self::etapas($c['etapas'] ?? []),
            'campo_preenchido' => CamposDoLead::rotulo((string) ($c['campo'] ?? '')) . ' preenchido',
            'campo_igual' => CamposDoLead::rotulo((string) ($c['campo'] ?? '')) . ' = “' . ($c['valor'] ?? '') . '”',
            'tem_tag' => 'Lead tem a tag ' . ($c['tag'] ?? ''),
            default => 'Condição desconhecida',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $condicoes
     */
    public static function condicoes(?array $condicoes): ?string
    {
        if (empty($condicoes)) {
            return null;
        }

        return 'Se: ' . implode(' e ', array_map(self::condicao(...), $condicoes));
    }

    /**
     * @param  array<string, mixed>  $a
     */
    public static function acao(array $a): string
    {
        return match ($a['tipo'] ?? null) {
            'mover_etapa' => 'Mover para ' . (EtapaCanonica::tryFrom((string) ($a['etapa'] ?? ''))?->getLabel() ?? '?'),
            'adicionar_tag' => 'Adicionar a tag ' . ($a['tag'] ?? ''),
            'remover_tags' => 'Remover as tags ' . self::lista(array_map(strval(...), (array) ($a['tags'] ?? [])), 'e'),
            'preencher_campo' => 'Preencher ' . CamposDoLead::rotulo((string) ($a['campo'] ?? '')) . ' com “' . ($a['valor'] ?? '') . '”',
            'criar_tarefa' => 'Criar tarefa “' . ($a['texto'] ?? '') . '”',
            'criar_card_pipeline' => 'Criar card no Fluxo cirurgias',
            'iniciar_regua' => 'Iniciar régua de follow-up',
            'cancelar_reguas' => 'Cancelar follow-ups pendentes',
            'disparar_salesbot' => 'Disparar Salesbot',
            'criar_recebivel' => 'Gerar recebível no Financeiro',
            default => 'Ação desconhecida',
        };
    }

    /**
     * @param  mixed  $etapas
     */
    public static function etapas($etapas): string
    {
        $rotulos = array_map(
            fn ($e): string => EtapaCanonica::tryFrom((string) $e)?->getLabel() ?? (string) $e,
            is_array($etapas) ? array_values($etapas) : [],
        );

        return $rotulos === [] ? '(nenhuma etapa)' : self::lista($rotulos);
    }

    /**
     * "a, b ou c"
     *
     * @param  array<int, string>  $itens
     */
    public static function lista(array $itens, string $conjuncao = 'ou'): string
    {
        $itens = array_values($itens);

        if (count($itens) <= 1) {
            return $itens[0] ?? '';
        }

        $ultimo = array_pop($itens);

        return implode(', ', $itens) . " {$conjuncao} {$ultimo}";
    }
}
