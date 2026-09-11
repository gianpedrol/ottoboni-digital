<x-filament-panels::page>
    @php
        $ativa = $this->versaoAtiva();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            @if ($ativa)
                <x-filament::badge color="success" size="lg">Versão {{ $ativa->versao }} no ar</x-filament::badge>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    publicada por {{ $ativa->autor?->name ?? '—' }}
                    {{ $ativa->created_at?->diffForHumans() }}
                </span>
            @else
                <x-filament::badge color="danger" size="lg">Nenhuma versão publicada</x-filament::badge>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Enquanto não houver instruções publicadas, a agente escala tudo para a equipe.
                </span>
            @endif
        </div>

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

    {{-- Regras travadas: aparecem para consulta, sem campo de edição. --}}
    <x-filament::section collapsible>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedLockClosed" class="h-4 w-4" />
                Regras inegociáveis
            </span>
        </x-slot>

        <x-slot name="description">
            Não são editáveis por aqui — vão sempre no fim do prompt e prevalecem sobre qualquer
            instrução escrita abaixo. Mudança nelas é decisão clínica e passa por quem mantém o sistema.
        </x-slot>

        <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-500 dark:text-gray-400">
            @forelse ($this->guardrails() as $regra)
                <li>{{ $regra }}</li>
            @empty
                <li class="list-none text-danger-600">
                    Nenhuma regra cadastrada. Rode <code>php artisan db:seed --class=IaGuardrailSeeder</code>.
                </li>
            @endforelse
        </ol>
    </x-filament::section>

    <form wire:submit="publicar">
        {{ $this->form }}

        <div class="mt-4 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-rocket-launch">
                Publicar nova versão
            </x-filament::button>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                A versão atual continua valendo até a publicação terminar.
            </span>
        </div>
    </form>

    <x-filament::section :heading="'Histórico de versões'" collapsible collapsed>
        <div class="divide-y divide-gray-100 text-sm dark:divide-white/10">
            @foreach ($this->historico() as $versao)
                <div class="flex flex-wrap items-center gap-3 py-2">
                    <x-filament::badge :color="$versao->ativo ? 'success' : 'gray'">
                        v{{ $versao->versao }}
                    </x-filament::badge>
                    <span class="flex-1">
                        {{ $versao->motivo ?? 'sem motivo registrado' }}
                        <span class="text-gray-400">
                            — {{ $versao->autor?->name ?? '—' }},
                            {{ $versao->created_at?->format('d/m/Y H:i') }}
                        </span>
                    </span>

                    @unless ($versao->ativo)
                        <x-filament::button
                            size="xs"
                            color="gray"
                            wire:click="reverter({{ $versao->id }})"
                            wire:confirm="Republicar as instruções da versão {{ $versao->versao }}? Isso cria uma versão nova com o mesmo conteúdo."
                        >
                            Voltar para esta
                        </x-filament::button>
                    @endunless
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
