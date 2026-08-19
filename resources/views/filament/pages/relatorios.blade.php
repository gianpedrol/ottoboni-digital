<x-filament-panels::page>
    <form wire:submit="gerar">
        {{ $this->form }}

        <div class="mt-4 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-chart-bar">
                Gerar relatório
            </x-filament::button>

            @if ($this->relatorio && $this->podeExportar())
                <x-filament::button color="gray" icon="heroicon-o-document-arrow-down" wire:click="exportarPdf">
                    Exportar PDF completo
                </x-filament::button>
            @endif
        </div>
    </form>

    @if (count($this->snapshots))
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Relatórios gerados em segundo plano</x-slot>

            <ul class="space-y-2 text-sm">
                @foreach ($this->snapshots as $snapshot)
                    <li class="flex items-center gap-3">
                        <span class="text-gray-500">
                            {{ $snapshot->filtros['de'] ?? '' }} a {{ $snapshot->filtros['ate'] ?? '' }}
                            · {{ $snapshot->filtros['medico'] ?? '' }}
                        </span>
                        <x-filament::badge :color="match ($snapshot->status) {
                            'pronto' => 'success',
                            'erro' => 'danger',
                            default => 'warning',
                        }">
                            {{ $snapshot->status }}
                        </x-filament::badge>

                        @if ($snapshot->status === 'pronto')
                            <x-filament::link tag="button" wire:click="abrirSnapshot({{ $snapshot->id }})">
                                Abrir
                            </x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($this->relatorio)
        @php($r = $this->relatorio)

        {{-- Bloco 1: Volume --}}
        <x-filament::section>
            <x-slot name="heading">1 · Volume</x-slot>
            <x-slot name="headerEnd">
                @if ($this->podeExportar())
                    <x-filament::link tag="button" wire:click="exportarBloco('volume', 'csv')">CSV</x-filament::link>
                    <x-filament::link tag="button" wire:click="exportarBloco('volume', 'xlsx')">XLSX</x-filament::link>
                @endif
            </x-slot>

            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-2xl font-bold">{{ $r['volume']['total'] }}</p>
                    <p class="text-gray-500">leads no período</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $r['volume']['total_periodo_anterior'] }}</p>
                    <p class="text-gray-500">no período anterior ({{ $r['periodo']['dias'] }} dias)</p>
                </div>
                <div>
                    @if ($r['volume']['variacao_pct'] !== null)
                        <p class="text-2xl font-bold {{ $r['volume']['variacao_pct'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                            {{ $r['volume']['variacao_pct'] >= 0 ? '+' : '' }}{{ $r['volume']['variacao_pct'] }}%
                        </p>
                        <p class="text-gray-500">variação</p>
                    @else
                        <p class="text-2xl font-bold">—</p>
                        <p class="text-gray-500">sem base de comparação</p>
                    @endif
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1 pr-4">Dia</th>
                            <th class="py-1">Leads</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['volume']['por_dia'] as $dia => $total)
                            <tr class="border-t border-gray-100">
                                <td class="py-1 pr-4">{{ \Carbon\Carbon::parse($dia)->format('d/m (D)') }}</td>
                                <td class="py-1">{{ $total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Blocos 2 e 3: Origem e Temperatura --}}
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach (['origem' => '2 · Origem', 'temperatura' => '3 · Temperatura'] as $bloco => $titulo)
                <x-filament::section>
                    <x-slot name="heading">{{ $titulo }}</x-slot>
                    <x-slot name="headerEnd">
                        @if ($this->podeExportar())
                            <x-filament::link tag="button" wire:click="exportarBloco('{{ $bloco }}', 'csv')">CSV</x-filament::link>
                            <x-filament::link tag="button" wire:click="exportarBloco('{{ $bloco }}', 'xlsx')">XLSX</x-filament::link>
                        @endif
                    </x-slot>

                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($r[$bloco]['fatias'] as $nome => $fatia)
                                <tr class="border-t border-gray-100 first:border-0">
                                    <td class="py-1 pr-4">{{ $nome }}</td>
                                    <td class="py-1 pr-4 text-right font-medium">{{ $fatia['total'] }}</td>
                                    <td class="py-1 text-right text-gray-500">{{ $fatia['pct'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-filament::section>
            @endforeach
        </div>

        {{-- Bloco 4: Procedimentos --}}
        <x-filament::section>
            <x-slot name="heading">4 · Procedimentos mais buscados</x-slot>
            <x-slot name="headerEnd">
                @if ($this->podeExportar())
                    <x-filament::link tag="button" wire:click="exportarBloco('procedimentos', 'csv')">CSV</x-filament::link>
                    <x-filament::link tag="button" wire:click="exportarBloco('procedimentos', 'xlsx')">XLSX</x-filament::link>
                @endif
            </x-slot>

            <table class="w-full text-sm">
                <tbody>
                    @foreach ($r['procedimentos']['ranking'] as $nome => $fatia)
                        <tr class="border-t border-gray-100 first:border-0">
                            <td class="py-1 pr-4">{{ $nome }}</td>
                            <td class="py-1 pr-4 text-right font-medium">{{ $fatia['total'] }}</td>
                            <td class="py-1 text-right text-gray-500">{{ $fatia['pct'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        {{-- Bloco 5: Funil --}}
        <x-filament::section>
            <x-slot name="heading">5 · Funil por etapa</x-slot>
            <x-slot name="headerEnd">
                @if ($this->podeExportar())
                    <x-filament::link tag="button" wire:click="exportarBloco('funil', 'csv')">CSV</x-filament::link>
                    <x-filament::link tag="button" wire:click="exportarBloco('funil', 'xlsx')">XLSX</x-filament::link>
                @endif
            </x-slot>

            @foreach ($r['funil']['funis'] as $funil)
                <div class="mb-6 last:mb-0">
                    <p class="mb-2 font-medium">
                        {{ $funil['nome'] }}
                        <span class="text-gray-500">
                            — {{ $funil['total'] }} leads · {{ $funil['ganhos'] }} ganhos ({{ $funil['taxa_ganho_pct'] }}%) · {{ $funil['perdidos'] }} perdidos
                        </span>
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-4">Etapa</th>
                                    <th class="py-1 pr-4">Estão nela</th>
                                    <th class="py-1 pr-4">Chegaram até ela</th>
                                    <th class="py-1">Taxa de passagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($funil['etapas'] as $etapa)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-1 pr-4">{{ $etapa['nome'] }}</td>
                                        <td class="py-1 pr-4">{{ $etapa['atual'] }}</td>
                                        <td class="py-1 pr-4">{{ $etapa['chegaram'] }}</td>
                                        <td class="py-1">{{ $etapa['taxa_passagem_pct'] !== null ? $etapa['taxa_passagem_pct'] . '%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </x-filament::section>

        {{-- Bloco 6: Follow-up --}}
        <x-filament::section>
            <x-slot name="heading">6 · Follow-up</x-slot>

            <div class="grid grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-2xl font-bold">{{ $r['followup']['enviados'] }}</p>
                    <p class="text-gray-500">enviados</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $r['followup']['taxa_entrega_pct'] !== null ? $r['followup']['taxa_entrega_pct'] . '%' : '—' }}</p>
                    <p class="text-gray-500">taxa de entrega</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $r['followup']['taxa_resposta_pct'] !== null ? $r['followup']['taxa_resposta_pct'] . '%' : '—' }}</p>
                    <p class="text-gray-500">responderam em 72h</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $r['followup']['reativados'] ?? '—' }}</p>
                    <p class="text-gray-500">reativados (Fase 3)</p>
                </div>
            </div>

            @if (count($r['followup']['por_canal']))
                <p class="mt-4 text-sm text-gray-500">
                    Por canal:
                    @foreach ($r['followup']['por_canal'] as $canal => $qtd)
                        {{ $canal }}: {{ $qtd }}@if (! $loop->last) · @endif
                    @endforeach
                </p>
            @endif
        </x-filament::section>

        {{-- Bloco 7: Produtividade --}}
        <x-filament::section>
            <x-slot name="heading">7 · Produtividade</x-slot>
            <x-slot name="headerEnd">
                @if ($this->podeExportar())
                    <x-filament::link tag="button" wire:click="exportarBloco('produtividade', 'csv')">CSV</x-filament::link>
                    <x-filament::link tag="button" wire:click="exportarBloco('produtividade', 'xlsx')">XLSX</x-filament::link>
                @endif
            </x-slot>

            <table class="w-full text-sm">
                <tbody>
                    @foreach ($r['produtividade']['por_responsavel'] as $nome => $total)
                        <tr class="border-t border-gray-100 first:border-0">
                            <td class="py-1 pr-4">{{ $nome }}</td>
                            <td class="py-1 text-right font-medium">{{ $total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="mt-3 text-sm text-gray-500">
                Tempo médio até a primeira nota humana:
                {{ $r['produtividade']['tempo_medio_primeira_nota_horas'] !== null
                    ? $r['produtividade']['tempo_medio_primeira_nota_horas'] . 'h'
                    : '—' }}
                @if ($r['produtividade']['observacao'])
                    <br>{{ $r['produtividade']['observacao'] }}
                @endif
            </p>
        </x-filament::section>

        <p class="text-xs text-gray-400">
            Gerado em {{ \Carbon\Carbon::parse($r['gerado_em'])->format('d/m/Y H:i') }}
            · Regras dos números em docs/metricas.md
        </p>
    @endif
</x-filament-panels::page>
