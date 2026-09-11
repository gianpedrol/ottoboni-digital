<x-filament-panels::page>
    @php
        $geral = $this->geral();
        $cfg = $this->config();
        $dist = $this->distribuicao();
        $pct = fn (?float $v) => $v === null ? '—' : number_format($v * 100, 1) . '%';
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-filament::badge color="gray" size="lg">
            Modo: {{ $this->modoAtual()->getLabel() }}
        </x-filament::badge>

        @if (count($this->agentes()) > 1)
            <select
                wire:model.live="doctorId"
                class="fi-input block w-64 rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
            >
                @foreach ($this->agentes() as $id => $rotulo)
                    <option value="{{ $id }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section compact>
            <p class="text-sm text-gray-500 dark:text-gray-400">Acurácia</p>
            <p class="mt-1 text-3xl font-semibold">{{ $pct($geral['acuracia']) }}</p>
            <p class="mt-1 text-xs text-gray-400">
                limiar para liberar: {{ $pct($cfg->limiar_acuracia) }}
            </p>
        </x-filament::section>

        <x-filament::section compact>
            <p class="text-sm text-gray-500 dark:text-gray-400">Aprovado sem editar</p>
            <p class="mt-1 text-3xl font-semibold">{{ $pct($geral['taxa_sem_edicao']) }}</p>
            <p class="mt-1 text-xs text-gray-400">o número honesto para a cliente</p>
        </x-filament::section>

        <x-filament::section compact>
            <p class="text-sm text-gray-500 dark:text-gray-400">Revisões na janela</p>
            <p class="mt-1 text-3xl font-semibold">{{ $geral['amostras'] }}</p>
            <p class="mt-1 text-xs text-gray-400">
                janela de {{ $cfg->janela }} · mínimo {{ $cfg->min_amostras }} por assunto
            </p>
        </x-filament::section>

        <x-filament::section compact>
            <p class="text-sm text-gray-500 dark:text-gray-400">Rejeitadas</p>
            <p class="mt-1 text-3xl font-semibold">{{ $geral['rejeitadas'] }}</p>
            <p class="mt-1 text-xs text-gray-400">
                1 rejeição segura o assunto na fila
            </p>
        </x-filament::section>
    </div>

    @if ($geral['acuracia'] !== null && $geral['acuracia'] < $cfg->kill_switch_acuracia)
        <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm dark:border-danger-500/30 dark:bg-danger-500/10">
            <p class="font-semibold text-danger-700 dark:text-danger-400">Freio de mão acionado</p>
            <p class="mt-1 text-danger-700/80 dark:text-danger-400/80">
                A acurácia geral está abaixo de {{ $pct($cfg->kill_switch_acuracia) }}, então tudo voltou
                para a fila automaticamente — inclusive assuntos que já estavam liberados. Volta ao normal
                sozinho quando a nota subir.
            </p>
        </div>
    @endif

    <x-filament::section :heading="'Por assunto'">
        <x-slot name="description">
            Um assunto só é liberado com {{ $cfg->min_amostras }}+ revisões,
            acurácia ≥ {{ $pct($cfg->limiar_acuracia) }} e zero rejeições na janela.
            "Não sei responder" nunca é liberado.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-4">Assunto</th>
                        <th class="py-2 pr-4">Revisões</th>
                        <th class="py-2 pr-4">Acurácia</th>
                        <th class="py-2 pr-4">Sem editar</th>
                        <th class="py-2 pr-4">Rejeitadas</th>
                        <th class="py-2">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->porIntent() as $linha)
                        <tr class="border-t border-gray-100 dark:border-white/10">
                            <td class="py-2 pr-4 font-medium">{{ $linha['rotulo'] }}</td>
                            <td class="py-2 pr-4">{{ $linha['amostras'] }}</td>
                            <td class="py-2 pr-4">{{ $pct($linha['acuracia']) }}</td>
                            <td class="py-2 pr-4">{{ $pct($linha['taxa_sem_edicao']) }}</td>
                            <td class="py-2 pr-4">{{ $linha['rejeitadas'] }}</td>
                            <td class="py-2">
                                <x-filament::badge :color="$linha['liberado'] ? 'success' : 'warning'">
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
        <x-filament::section :heading="'Onde ela erra'">
            <x-slot name="description">Distribuição das últimas {{ $cfg->janela }} revisões.</x-slot>

            <dl class="space-y-2 text-sm">
                @foreach ([
                    'sem_edicao' => ['Aprovado sem editar', 'success'],
                    'leve' => ['Ajuste leve', 'info'],
                    'media' => ['Ajuste grande', 'warning'],
                    'refeita' => ['Refeita do zero', 'danger'],
                    'rejeitadas' => ['Rejeitada (silêncio)', 'gray'],
                ] as $chave => [$rotulo, $cor])
                    <div class="flex items-center gap-3">
                        <dt class="w-44 shrink-0">{{ $rotulo }}</dt>
                        <dd class="flex flex-1 items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div
                                    class="h-full rounded-full bg-{{ $cor }}-500"
                                    style="width: {{ $cfg->janela > 0 ? min(100, ($dist[$chave] / $cfg->janela) * 100) : 0 }}%"
                                ></div>
                            </div>
                            <span class="w-8 text-right font-medium">{{ $dist[$chave] }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <x-filament::section :heading="'Buracos da base'">
            <x-slot name="description">
                As perguntas que mais caíram em "não sei responder". Cada uma que a equipe
                responde vira card e sai desta lista.
            </x-slot>

            @if ($this->lacunas() === [])
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">
                    Nenhuma lacuna registrada — a base cobriu tudo que perguntaram até agora.
                </p>
            @else
                <ol class="space-y-1 text-sm">
                    @foreach ($this->lacunas() as $lacuna)
                        <li class="flex items-start gap-2">
                            <x-filament::badge color="danger" size="sm">{{ $lacuna['vezes'] }}x</x-filament::badge>
                            <span class="flex-1">{{ \Illuminate\Support\Str::limit($lacuna['pergunta'], 120) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
