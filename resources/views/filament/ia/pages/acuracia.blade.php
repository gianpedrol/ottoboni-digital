@php
    use App\Filament\Ia\Pages\ConfiguracaoDaIa;
    use App\Filament\Ia\Pages\FilaDeAprovacao;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Str;

    // Classes completas aqui (e não montadas por string) para o Tailwind enxergar.
    $barras = [
        'success' => 'bg-success-500',
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        'gray' => 'bg-gray-400',
    ];

    $textos = [
        'success' => 'text-success-700',
        'warning' => 'text-warning-700',
        'danger' => 'text-danger-700',
        'gray' => 'text-gray-500',
    ];

    $situacoes = [
        'success' => 'Acima do limiar',
        'warning' => 'Abaixo do limiar',
        'danger' => 'Freio acionado',
        'gray' => 'Sem dados',
    ];
@endphp

<x-filament-panels::page>
    @php
        $geral = $this->geral();
        $cfg = $this->config();
        $dist = $this->distribuicao();
        $lacunas = $this->lacunas();
        $modo = $cfg->modo;

        $pct = fn (?float $v): string => $v === null ? '—' : number_format($v * 100, 1, ',', '.') . '%';

        // Onde a nota está em relação aos dois limites que importam.
        $tom = fn (?float $v): string => match (true) {
            $v === null => 'gray',
            $v >= $cfg->limiar_acuracia => 'success',
            $v < $cfg->kill_switch_acuracia => 'danger',
            default => 'warning',
        };

        $tomGeral = $tom($geral['acuracia']);
        $totalDist = array_sum($dist);
    @endphp

    {{-- Modo atual + seletor de agente --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <x-filament::badge :color="$modo->getColor()" size="lg" :icon="Heroicon::OutlinedShieldCheck">
                Modo {{ Str::lower($modo->getLabel()) }}
            </x-filament::badge>
            <span class="text-sm text-gray-500">{{ $modo->getDescription() }}</span>
            @if (ConfiguracaoDaIa::canAccess())
                <x-filament::link :href="ConfiguracaoDaIa::getUrl()" size="sm" :icon="Heroicon::OutlinedAdjustmentsHorizontal">
                    Alterar
                </x-filament::link>
            @endif
        </div>

        @include('filament.ia.partials.seletor-agente', ['agentes' => $this->agentes()])
    </div>

    @if ($tomGeral === 'danger')
        <div role="alert" class="flex gap-3 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm">
            <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="h-5 w-5 shrink-0 text-danger-600" />
            <div>
                <p class="font-semibold text-danger-800">Freio de mão acionado</p>
                <p class="mt-1 text-danger-700">
                    A acurácia geral está abaixo de {{ $pct($cfg->kill_switch_acuracia) }}, então tudo voltou
                    para a fila automaticamente — inclusive assuntos que já estavam liberados. Volta ao normal
                    sozinho quando a nota subir.
                </p>
            </div>
        </div>
    @endif

    @if ($geral['amostras'] === 0)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-info-200 bg-info-50 p-4 text-sm">
            <div class="flex gap-3">
                <x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="h-5 w-5 shrink-0 text-info-600" />
                <div>
                    <p class="font-semibold text-info-800">Ainda não há nota</p>
                    <p class="mt-1 text-info-700">
                        A acurácia sai das revisões feitas na fila. Depois das primeiras aprovações e
                        rejeições, os números aparecem aqui.
                    </p>
                </div>
            </div>
            <x-filament::button tag="a" :href="FilaDeAprovacao::getUrl()" color="info" size="sm" :icon="Heroicon::OutlinedInboxArrowDown">
                Ir para a fila
            </x-filament::button>
        </div>
    @endif

    {{-- ---------- Os quatro números ---------- --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-filament::section compact>
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Acurácia</p>
                <x-filament::badge :color="$tomGeral" size="sm">{{ $situacoes[$tomGeral] }}</x-filament::badge>
            </div>
            <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-gray-950">
                {{ $pct($geral['acuracia']) }}
            </p>

            {{-- Régua 0–100% com as marcas do limiar e do freio --}}
            <div class="relative mt-4 h-2 rounded-full bg-gray-100" aria-hidden="true">
                <div
                    class="h-full rounded-full {{ $barras[$tomGeral] }}"
                    style="width: {{ min(100, ($geral['acuracia'] ?? 0) * 100) }}%"
                ></div>
                <span
                    class="absolute -top-1 h-4 w-0.5 rounded-full bg-danger-500"
                    style="left: {{ $cfg->kill_switch_acuracia * 100 }}%"
                ></span>
                <span
                    class="absolute -top-1 h-4 w-0.5 rounded-full bg-gray-800"
                    style="left: {{ $cfg->limiar_acuracia * 100 }}%"
                ></span>
            </div>
            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-3 w-0.5 rounded-full bg-gray-800"></span>
                    libera com {{ $pct($cfg->limiar_acuracia) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-3 w-0.5 rounded-full bg-danger-500"></span>
                    freio abaixo de {{ $pct($cfg->kill_switch_acuracia) }}
                </span>
            </div>
        </x-filament::section>

        <x-filament::section compact>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Aprovado sem editar</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-gray-950">
                {{ $pct($geral['taxa_sem_edicao']) }}
            </p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                <div
                    class="h-full rounded-full bg-success-500"
                    style="width: {{ min(100, ($geral['taxa_sem_edicao'] ?? 0) * 100) }}%"
                ></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">O número honesto para mostrar à cliente.</p>
        </x-filament::section>

        <x-filament::section compact>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Revisões na janela</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-gray-950">
                {{ $geral['amostras'] }}
                <span class="text-base font-normal text-gray-400">de {{ $cfg->janela }}</span>
            </p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                <div
                    class="h-full rounded-full bg-primary-500"
                    style="width: {{ $cfg->janela > 0 ? min(100, $geral['amostras'] / $cfg->janela * 100) : 0 }}%"
                ></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Mínimo de {{ $cfg->min_amostras }} por assunto para liberar.
            </p>
        </x-filament::section>

        <x-filament::section compact>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Rejeitadas</p>
            <p @class([
                'mt-2 text-3xl font-semibold tracking-tight tabular-nums',
                'text-danger-700' => $geral['rejeitadas'] > 0,
                'text-gray-950' => $geral['rejeitadas'] === 0,
            ])>
                {{ $geral['rejeitadas'] }}
            </p>
            <p class="mt-4 text-xs text-gray-500">
                Uma rejeição na janela já segura o assunto na fila.
            </p>
        </x-filament::section>
    </div>

    {{-- ---------- Por assunto ---------- --}}
    <x-filament::section heading="Por assunto" :icon="Heroicon::OutlinedTableCells">
        <x-slot name="description">
            Um assunto só é liberado com {{ $cfg->min_amostras }}+ revisões,
            acurácia de pelo menos {{ $pct($cfg->limiar_acuracia) }} e zero rejeições na janela.
            "Não sei responder" nunca é liberado.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th scope="col" class="py-2 pe-4">Assunto</th>
                        <th scope="col" class="py-2 pe-4">Revisões</th>
                        <th scope="col" class="py-2 pe-4">Acurácia</th>
                        <th scope="col" class="py-2 pe-4">Sem editar</th>
                        <th scope="col" class="py-2 pe-4">Rejeitadas</th>
                        <th scope="col" class="py-2">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($this->porIntent() as $linha)
                        @php
                            $progresso = $cfg->min_amostras > 0 ? min(100, $linha['amostras'] / $cfg->min_amostras * 100) : 100;
                        @endphp
                        <tr>
                            <th scope="row" class="py-3 pe-4 text-left font-medium text-gray-950">{{ $linha['rotulo'] }}</th>
                            <td class="py-3 pe-4">
                                <div class="flex items-center gap-2">
                                    <span class="w-14 tabular-nums text-gray-950">
                                        {{ $linha['amostras'] }}<span class="text-gray-400">/{{ $cfg->min_amostras }}</span>
                                    </span>
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                        <div
                                            @class([
                                                'h-full rounded-full',
                                                'bg-success-500' => $progresso >= 100,
                                                'bg-primary-400' => $progresso < 100,
                                            ])
                                            style="width: {{ $progresso }}%"
                                        ></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 pe-4 font-medium tabular-nums {{ $textos[$tom($linha['acuracia'])] ?? 'text-gray-950' }}">
                                {{ $pct($linha['acuracia']) }}
                            </td>
                            <td class="py-3 pe-4 tabular-nums text-gray-700">{{ $pct($linha['taxa_sem_edicao']) }}</td>
                            <td @class([
                                'py-3 pe-4 tabular-nums',
                                'font-medium text-danger-700' => $linha['rejeitadas'] > 0,
                                'text-gray-700' => $linha['rejeitadas'] === 0,
                            ])>
                                {{ $linha['rejeitadas'] }}
                            </td>
                            <td class="py-3">
                                <x-filament::badge
                                    :color="$linha['liberado'] ? 'success' : 'warning'"
                                    :icon="$linha['liberado'] ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock"
                                >
                                    {{ $linha['situacao'] }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- ---------- Onde ela erra ---------- --}}
        <x-filament::section heading="Onde ela erra" :icon="Heroicon::OutlinedChartBar">
            <x-slot name="description">
                Quanto a equipe precisou mexer nas últimas {{ $totalDist }} revisões
                (a janela vai até {{ $cfg->janela }}).
            </x-slot>

            @if ($totalDist === 0)
                <p class="py-4 text-sm text-gray-500">Sem revisões na janela ainda.</p>
            @else
                <dl class="space-y-4 text-sm">
                    @foreach ([
                        'sem_edicao' => ['Aprovado sem editar', 'success'],
                        'leve' => ['Ajuste leve', 'info'],
                        'media' => ['Ajuste grande', 'warning'],
                        'refeita' => ['Refeita do zero', 'danger'],
                        'rejeitadas' => ['Rejeitada (silêncio)', 'gray'],
                    ] as $chave => [$rotulo, $cor])
                        @php $parte = $dist[$chave] / $totalDist * 100; @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-700">{{ $rotulo }}</dt>
                                <dd class="tabular-nums font-medium text-gray-950">
                                    {{ $dist[$chave] }}
                                    <span class="ms-1 text-xs font-normal text-gray-400">{{ number_format($parte, 0) }}%</span>
                                </dd>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                <div class="h-full rounded-full {{ $barras[$cor] }}" style="width: {{ $parte }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-filament::section>

        {{-- ---------- Buracos da base ---------- --}}
        <x-filament::section heading="Buracos da base" :icon="Heroicon::OutlinedQuestionMarkCircle">
            <x-slot name="description">
                As perguntas que mais caíram em "não sei responder". Cada uma que a equipe
                responde na fila vira card e sai desta lista.
            </x-slot>

            @if ($lacunas === [])
                <div class="flex items-center gap-3 py-4 text-sm text-gray-500">
                    <x-filament::icon :icon="Heroicon::OutlinedCheckCircle" class="h-5 w-5 text-success-500" />
                    Nenhuma lacuna registrada — a base cobriu tudo que perguntaram até agora.
                </div>
            @else
                <ol class="divide-y divide-gray-100">
                    @foreach ($lacunas as $lacuna)
                        <li class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                            <x-filament::badge color="danger" size="sm" class="shrink-0 tabular-nums">
                                {{ $lacuna['vezes'] }}×
                            </x-filament::badge>
                            <span class="text-sm text-gray-700">{{ Str::limit($lacuna['pergunta'], 140) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
