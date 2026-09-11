<x-filament-panels::page>
    <form wire:submit="simular">
        {{ $this->form }}

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-beaker">
                Simular (não grava nada)
            </x-filament::button>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                O simulador só calcula o que a regra faria. Nenhuma chamada de escrita vai para o Kommo.
            </span>
        </div>
    </form>

    @if ($this->resultado !== null)
        @php
            $r = $this->resultado;
            $passo = 0;
        @endphp

        <x-filament::section>
            <x-slot name="heading">{{ $r['regra'] }} × {{ $r['lead']['nome'] }}</x-slot>
            <x-slot name="description">{{ $r['lead']['resumo'] }} · regra em modo “{{ $r['modo'] }}”</x-slot>

            {{-- Lead usado --}}
            <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $r['lead']['fonte'] }}</p>

                <div class="mt-2 flex flex-wrap gap-1">
                    @forelse ($r['lead']['tags'] as $tag)
                        <x-filament::badge color="gray">{{ $tag }}</x-filament::badge>
                    @empty
                        <span class="text-xs text-gray-500 dark:text-gray-400">sem tags</span>
                    @endforelse
                </div>

                @if (count($r['lead']['campos']))
                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($r['lead']['campos'] as $rotulo => $valor)
                            <div class="flex gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $rotulo }}:</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            {{-- Veredito --}}
            <div @class([
                'mt-4 rounded-lg p-4 text-sm font-medium ring-1',
                'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30' => $r['casou'],
                'bg-gray-50 text-gray-700 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! $r['casou'],
            ])>
                {{ $r['motivo'] }}
            </div>

            <ol class="mt-6 space-y-6">
                {{-- 1. Pipeline --}}
                <li class="flex gap-3">
                    <x-filament::icon
                        :icon="$r['pipeline']['ok'] ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle'"
                        @class(['h-6 w-6 shrink-0', 'text-success-600' => $r['pipeline']['ok'], 'text-danger-600' => ! $r['pipeline']['ok']])
                    />
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ++$passo }}. Pipeline</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $r['pipeline']['motivo'] }}</p>
                    </div>
                </li>

                {{-- Anti-loop --}}
                @if ($r['anti_loop']['ignorado'])
                    <li class="flex gap-3">
                        <x-filament::icon icon="heroicon-s-arrow-path" class="h-6 w-6 shrink-0 text-warning-600" />
                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ++$passo }}. Anti-loop</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $r['anti_loop']['motivo'] }}</p>
                        </div>
                    </li>
                @endif

                @if ($r['pipeline']['ok'] && ! $r['anti_loop']['ignorado'])
                    {{-- 2. Gatilho --}}
                    <li class="flex gap-3">
                        <x-filament::icon
                            :icon="$r['gatilho']['ok'] ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle'"
                            @class(['h-6 w-6 shrink-0', 'text-success-600' => $r['gatilho']['ok'], 'text-danger-600' => ! $r['gatilho']['ok']])
                        />
                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ++$passo }}. Gatilho</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Evento: {{ $r['evento']['descricao'] ?? '—' }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $r['gatilho']['motivo'] }}</p>
                        </div>
                    </li>

                    {{-- 3. Condições --}}
                    <li class="flex gap-3">
                        <x-filament::icon icon="heroicon-s-funnel" class="h-6 w-6 shrink-0 text-gray-400" />
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ++$passo }}. Condições</p>

                            @forelse ($r['condicoes'] as $condicao)
                                <div class="mt-1 flex items-start gap-2 text-sm">
                                    <x-filament::icon
                                        :icon="$condicao['ok'] ? 'heroicon-m-check' : 'heroicon-m-x-mark'"
                                        @class(['mt-0.5 h-4 w-4 shrink-0', 'text-success-600' => $condicao['ok'], 'text-danger-600' => ! $condicao['ok']])
                                    />
                                    <span>
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $condicao['descricao'] }}</span>
                                        <span class="text-gray-600 dark:text-gray-300">— {{ $condicao['motivo'] }}</span>
                                    </span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-600 dark:text-gray-300">Sem condições: basta o gatilho.</p>
                            @endforelse
                        </div>
                    </li>

                    {{-- 4. Ações --}}
                    <li class="flex gap-3">
                        <x-filament::icon icon="heroicon-s-bolt" @class(['h-6 w-6 shrink-0', 'text-primary-600' => $r['casou'], 'text-gray-400' => ! $r['casou']]) />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ++$passo }}. Ações</p>

                            @if ($r['casou'])
                                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                                    Em ordem, com a chamada exata que iria para o Kommo. Campos personalizados aparecem como {Nome do campo}: o ID é resolvido pelo nome na execução real.
                                </p>
                                @include('filament.automacoes.acoes', ['acoes' => $r['acoes']])
                            @else
                                <p class="text-sm text-gray-600 dark:text-gray-300">Nenhuma: a regra não casou com este lead e evento.</p>
                            @endif
                        </div>
                    </li>
                @endif
            </ol>

            @if ($r['casou'])
                <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                    @if ($this->registrado)
                        <x-filament::badge color="success" icon="heroicon-m-check">Registrada no log de execuções</x-filament::badge>
                    @else
                        <x-filament::button wire:click="registrar" color="gray" icon="heroicon-o-clipboard-document-check">
                            Registrar execução simulada
                        </x-filament::button>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Grava só no painel, com status “Só registrado”.</span>
                    @endif
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
