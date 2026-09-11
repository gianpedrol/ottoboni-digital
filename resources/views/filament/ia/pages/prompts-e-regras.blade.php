@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    @php
        $ativa = $this->versaoAtiva();
        $historico = $this->historico();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            @if ($ativa)
                <x-filament::badge color="success" size="lg" :icon="Heroicon::OutlinedSignal">
                    Versão {{ $ativa->versao }} no ar
                </x-filament::badge>
                <span class="text-sm text-gray-500">
                    publicada por <span class="font-medium text-gray-700">{{ $ativa->autor?->name ?? '—' }}</span>
                    {{ $ativa->created_at?->diffForHumans() }}
                </span>
            @else
                <x-filament::badge color="danger" size="lg" :icon="Heroicon::OutlinedExclamationTriangle">
                    Nenhuma versão publicada
                </x-filament::badge>
                <span class="text-sm text-gray-500">
                    Enquanto não houver instruções publicadas, a agente escala tudo para a equipe.
                </span>
            @endif
        </div>

        @include('filament.ia.partials.seletor-agente', ['agentes' => $this->agentes()])
    </div>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">
        {{-- ---------- Edição ---------- --}}
        <form wire:submit="publicar" class="space-y-6 xl:col-span-2">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit" :icon="Heroicon::OutlinedRocketLaunch" wire:target="publicar">
                    Publicar nova versão
                </x-filament::button>
                <span class="text-sm text-gray-500">
                    A versão atual continua valendo até a publicação terminar.
                </span>
            </div>
        </form>

        {{-- ---------- Consulta ---------- --}}
        <div class="space-y-6">
            {{-- Regras travadas: aparecem para consulta, sem campo de edição. --}}
            <x-filament::section
                heading="Regras inegociáveis"
                :icon="Heroicon::OutlinedLockClosed"
                compact
                collapsible
            >
                <x-slot name="description">
                    Vão sempre no fim do prompt e prevalecem sobre qualquer instrução ao lado. Mudança
                    nelas é decisão clínica e passa por quem mantém o sistema.
                </x-slot>

                @php $regras = $this->guardrails(); @endphp

                @if ($regras === [])
                    <p class="text-sm text-danger-600">
                        Nenhuma regra cadastrada. Rode
                        <code class="rounded bg-danger-50 px-1 font-mono text-xs">php artisan db:seed --class=IaGuardrailSeeder</code>.
                    </p>
                @else
                    <ol class="space-y-2 text-sm text-gray-600">
                        @foreach ($regras as $regra)
                            <li class="flex gap-2.5">
                                <span class="mt-px inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium tabular-nums text-gray-500">
                                    {{ $loop->iteration }}
                                </span>
                                <span>{{ $regra }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-filament::section>

            <x-filament::section heading="Histórico de versões" :icon="Heroicon::OutlinedClock" compact>
                @if ($historico->isEmpty())
                    <p class="text-sm text-gray-500">Nenhuma versão publicada ainda.</p>
                @else
                    <ol class="relative ms-1.5 space-y-5 border-s border-gray-200">
                        @foreach ($historico as $versao)
                            <li class="relative ps-5">
                                <span @class([
                                    'absolute -start-[5px] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white',
                                    'bg-success-500' => $versao->ativo,
                                    'bg-gray-300' => ! $versao->ativo,
                                ])></span>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-950">v{{ $versao->versao }}</span>
                                    @if ($versao->ativo)
                                        <x-filament::badge color="success" size="sm">no ar</x-filament::badge>
                                    @endif
                                </div>

                                <p class="mt-0.5 text-sm text-gray-700">
                                    {{ $versao->motivo ?? 'sem motivo registrado' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $versao->autor?->name ?? '—' }} · {{ $versao->created_at?->format('d/m/Y H:i') }}
                                </p>

                                @unless ($versao->ativo)
                                    <x-filament::link
                                        tag="button"
                                        size="sm"
                                        color="gray"
                                        :icon="Heroicon::OutlinedArrowUturnLeft"
                                        class="mt-1"
                                        wire:click="reverter({{ $versao->id }})"
                                        wire:confirm="Republicar as instruções da versão {{ $versao->versao }}? Isso cria uma versão nova com o mesmo conteúdo."
                                    >
                                        Voltar para esta
                                    </x-filament::link>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
