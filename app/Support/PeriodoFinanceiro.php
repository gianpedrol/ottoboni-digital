<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Atalhos de período do Fluxo de caixa e o filtro de médico, lidos pelos
 * widgets a partir de $pageFilters.
 */
class PeriodoFinanceiro
{
    /**
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return [
            'mes' => 'Mês atual',
            'mes_anterior' => 'Mês anterior',
            '3m' => 'Últimos 3 meses',
            '6m' => 'Últimos 6 meses',
            'ano' => 'Ano atual',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filtros
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function intervalo(?array $filtros): array
    {
        $hoje = Financeiro::hoje();

        return match ($filtros['periodo'] ?? 'mes') {
            'mes_anterior' => [$hoje->subMonthNoOverflow()->startOfMonth(), $hoje->subMonthNoOverflow()->endOfMonth()],
            '3m' => [$hoje->subMonthsNoOverflow(2)->startOfMonth(), $hoje->endOfMonth()],
            '6m' => [$hoje->subMonthsNoOverflow(5)->startOfMonth(), $hoje->endOfMonth()],
            'ano' => [$hoje->startOfYear(), $hoje->endOfYear()],
            default => [$hoje->startOfMonth(), $hoje->endOfMonth()],
        };
    }

    /**
     * @param  array<string, mixed>|null  $filtros
     */
    public static function rotulo(?array $filtros): string
    {
        return self::opcoes()[$filtros['periodo'] ?? 'mes'] ?? 'Mês atual';
    }

    /**
     * @param  array<string, mixed>|null  $filtros
     */
    public static function medico(?array $filtros): ?int
    {
        $medico = $filtros['medico'] ?? null;

        return filled($medico) ? (int) $medico : null;
    }
}
