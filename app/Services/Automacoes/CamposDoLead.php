<?php

namespace App\Services\Automacoes;

use App\Services\Kommo\DTO\LeadData;

/**
 * Campos do lead que as automações leem e escrevem. A chave é a mesma de
 * config('kommo.custom_fields') quando o campo já é lido pelo painel.
 */
final class CamposDoLead
{
    /** @var array<string, string> */
    public const ROTULOS = [
        'proxima_consulta' => 'Próxima consulta',
        'comparecimento' => 'Comparecimento',
        'status_sinal' => 'Status do sinal',
        'valor_proposta' => 'Valor da proposta',
        'valor_total' => 'Valor total',
        'data_assinatura' => 'Data da assinatura',
        'data_cirurgia' => 'Data da cirurgia',
        'cirurgia' => 'Cirurgia',
        'hospital' => 'Hospital',
        'anestesista' => 'Anestesista',
        'protese' => 'Prótese',
        'medico_responsavel' => 'Médico responsável',
        'procedimento' => 'Procedimento de interesse',
        'temperatura' => 'Temperatura',
        'origem' => 'Origem do lead',
    ];

    /**
     * Opções padronizadas na reestruturação (escopo 1.1).
     *
     * @var array<string, array<int, string>>
     */
    public const OPCOES = [
        'comparecimento' => ['Compareceu', 'Faltou', 'Remarcou'],
        'status_sinal' => ['Pendente', 'Pago'],
        'temperatura' => ['Quente', 'Morno', 'Frio'],
    ];

    /**
     * Campos que o painel ainda não carrega do Kommo (LeadData não lê).
     *
     * @var array<int, string>
     */
    public const NAO_LIDOS = ['hospital', 'anestesista', 'protese'];

    public static function rotulo(string $campo): string
    {
        return self::ROTULOS[$campo] ?? $campo;
    }

    /**
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return self::ROTULOS;
    }

    /**
     * @return array<string, ?string>
     */
    public static function doLead(LeadData $lead): array
    {
        $tz = (string) config('painel.timezone');

        return [
            'proxima_consulta' => $lead->proximaConsulta?->setTimezone($tz)->format('d/m/Y H:i'),
            'comparecimento' => $lead->comparecimento,
            'status_sinal' => $lead->statusSinal,
            'valor_proposta' => $lead->valorProposta !== null ? self::numero($lead->valorProposta) : null,
            'valor_total' => $lead->valorTotal,
            'data_assinatura' => $lead->dataAssinatura,
            'data_cirurgia' => $lead->dataCirurgia,
            'cirurgia' => $lead->cirurgia,
            'medico_responsavel' => $lead->medicoResponsavel,
            'procedimento' => $lead->procedimento,
            'temperatura' => $lead->temperatura,
            'origem' => $lead->origem,
        ];
    }

    /**
     * Converte "R$ 32.000,00", "32000" ou "32000.5" em número.
     */
    public static function valorNumerico(?string $valor): ?float
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $limpo = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';

        if (str_contains($limpo, ',')) {
            $limpo = str_replace(['.', ','], ['', '.'], $limpo);
        }

        return is_numeric($limpo) ? (float) $limpo : null;
    }

    public static function moeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private static function numero(float $valor): string
    {
        return floor($valor) === $valor ? (string) (int) $valor : (string) $valor;
    }
}
