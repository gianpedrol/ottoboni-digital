<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Indicadores\ResultadosDoPeriodo;
use App\Support\PeriodoAtalho;
use App\Support\SelecaoMedico;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

/**
 * Os quatro números do topo: leads, consultas, novos contratos e cirurgias.
 */
class ResultadosWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Resultados do período';

    protected function getDescription(): ?string
    {
        return config('painel.prototipo')
            ? 'Leads ao vivo do Kommo · consultas, contratos e cirurgias em demonstração'
            : 'Comparação com o período anterior de mesmo tamanho';
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Auth::user();

        [$de, $ate] = PeriodoAtalho::resolver(
            $this->pageFilters['periodo'] ?? '30d',
            $this->pageFilters['de'] ?? null,
            $this->pageFilters['ate'] ?? null,
        );

        $r = app(ResultadosDoPeriodo::class)->calcular(
            SelecaoMedico::pipelineIds($user, $this->pageFilters['medico'] ?? null),
            $de,
            $ate,
        );

        // Valor de contrato é dado financeiro: recepção vê só a quantidade
        $veValores = $user->isAdmin() || $user->isGestor();

        return [
            $r['leads'] === null
                ? Stat::make('Leads', '—')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->description('Kommo indisponível')
                    ->color('gray')
                : $this->card('Leads', Heroicon::OutlinedUserPlus, $r['leads']),
            $this->card('Consultas', Heroicon::OutlinedCalendarDays, $r['consultas'],
                $r['consultas']['comparecimento_pct'] !== null
                    ? $this->pct($r['consultas']['comparecimento_pct']) . ' compareceram'
                    : null),
            $this->card('Novos contratos', Heroicon::OutlinedDocumentCheck, $r['contratos'],
                $veValores && $r['contratos']['valor'] > 0
                    ? (string) Number::currency($r['contratos']['valor'], in: 'BRL', locale: 'pt_BR', precision: 0)
                    : null),
            $this->card('Cirurgias', Heroicon::OutlinedScissors, $r['cirurgias'],
                "{$r['cirurgias']['proximas_30d']} nos próximos 30 dias"),
        ];
    }

    /**
     * @param  array{atual: int, anterior: int, variacao_pct: ?float, serie: array<int, int>}  $metrica
     */
    private function card(string $rotulo, Heroicon $icone, array $metrica, ?string $extra = null): Stat
    {
        $variacao = $metrica['variacao_pct'];

        $texto = $variacao === null
            ? 'sem base de comparação'
            : ($variacao > 0 ? '+' : '') . $this->pct($variacao) . ' vs anterior';

        return Stat::make($rotulo, Number::format($metrica['atual'], locale: 'pt_BR'))
            ->icon($icone)
            ->description($extra !== null ? "{$texto} · {$extra}" : $texto)
            ->descriptionIcon(match (true) {
                $variacao === null => null,
                $variacao > 0 => Heroicon::OutlinedArrowTrendingUp,
                $variacao < 0 => Heroicon::OutlinedArrowTrendingDown,
                default => Heroicon::OutlinedMinus,
            })
            ->color(match (true) {
                $variacao === null, $variacao == 0 => 'gray',
                $variacao > 0 => 'success',
                default => 'danger',
            })
            ->chart($metrica['serie'])
            ->chartColor('primary');
    }

    private function pct(float $valor): string
    {
        return Number::format($valor, maxPrecision: 1, locale: 'pt_BR') . '%';
    }
}
