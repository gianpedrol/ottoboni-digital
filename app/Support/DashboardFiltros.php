<?php

namespace App\Support;

use App\Models\User;
use App\Repositories\LeadFilters;

/**
 * Converte os filtros do dashboard (médico + período) em LeadFilters,
 * respeitando o escopo do usuário.
 */
class DashboardFiltros
{
    /**
     * @param  array<string, mixed>|null  $pageFilters
     */
    public static function montar(User $user, ?array $pageFilters): LeadFilters
    {
        [$de, $ate] = PeriodoAtalho::resolver(
            $pageFilters['periodo'] ?? '30d',
            $pageFilters['de'] ?? null,
            $pageFilters['ate'] ?? null,
        );

        return new LeadFilters(
            pipelineIds: SelecaoMedico::pipelineIds($user, $pageFilters['medico'] ?? null),
            from: $de->utc(),
            to: $ate->utc(),
        );
    }
}
