<x-filament-panels::page>
    <form wire:submit="simular">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-beaker">
                Simular (não envia nada)
            </x-filament::button>
        </div>
    </form>

    @if ($this->simulacao !== null)
        @if (! empty($this->simulacao['erro']))
            <x-filament::section>
                <p class="text-danger-600">{{ $this->simulacao['erro'] }}</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    Simulação — {{ $this->simulacao['regua'] ?? '' }}
                </x-slot>

                <div class="mb-4 text-sm text-gray-500">
                    Lead: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $this->simulacao['lead']['nome'] ?? '' }}</span>
                    @if ($this->simulacao['lead']['instagram'] ?? null)
                        · {{ $this->simulacao['lead']['instagram'] }}
                    @endif
                    @if ($this->simulacao['lead']['procedimento'] ?? null)
                        · {{ $this->simulacao['lead']['procedimento'] }}
                    @endif
                    @if ($this->simulacao['lead']['temperatura'] ?? null)
                        · {{ $this->simulacao['lead']['temperatura'] }}
                    @endif

                    @if ($this->simulacao['lead']['fechado'] ?? false)
                        <p class="mt-1 font-medium text-warning-600">
                            Atenção: este lead já foi ganho/perdido — na prática a régua não agendaria nada para ele.
                        </p>
                    @endif
                </div>

                <div class="space-y-4">
                    @foreach ($this->simulacao['passos'] as $passo)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <x-filament::badge>Passo {{ $passo['ordem'] }}</x-filament::badge>
                                <span class="font-medium">{{ $passo['previsto_para'] }}</span>
                                <span class="text-gray-500">· {{ $passo['canal'] }} · {{ $passo['modo'] }}</span>
                            </div>

                            <p class="mt-2 whitespace-pre-line text-sm">{{ $passo['texto'] }}</p>

                            @if ($passo['observacao'])
                                <p class="mt-2 text-xs text-warning-600">{{ $passo['observacao'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-gray-400">
                    Datas calculadas a partir de agora, ajustadas à janela de envio.
                    Nenhuma mensagem, tarefa ou webhook foi disparado por esta simulação.
                </p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
