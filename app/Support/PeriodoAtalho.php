<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Atalhos de período usados nas telas. Sempre no fuso America/Sao_Paulo;
 * a conversão para UTC acontece só na hora de montar o filtro da API.
 */
class PeriodoAtalho
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'hoje' => 'Hoje',
            '7d' => 'Últimos 7 dias',
            '30d' => 'Últimos 30 dias',
            'mes_passado' => 'Mês passado',
            'personalizado' => 'Personalizado',
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}  [início, fim] no fuso local
     */
    public static function resolver(?string $atalho, ?string $de = null, ?string $ate = null): array
    {
        $tz = config('painel.timezone');
        $hoje = CarbonImmutable::now($tz);

        return match ($atalho) {
            'hoje' => [$hoje->startOfDay(), $hoje->endOfDay()],
            '7d' => [$hoje->subDays(6)->startOfDay(), $hoje->endOfDay()],
            'mes_passado' => [
                $hoje->subMonthNoOverflow()->startOfMonth(),
                $hoje->subMonthNoOverflow()->endOfMonth(),
            ],
            'personalizado' => [
                filled($de) ? CarbonImmutable::parse($de, $tz)->startOfDay() : $hoje->subDays(29)->startOfDay(),
                filled($ate) ? CarbonImmutable::parse($ate, $tz)->endOfDay() : $hoje->endOfDay(),
            ],
            default => [$hoje->subDays(29)->startOfDay(), $hoje->endOfDay()],
        };
    }
}
