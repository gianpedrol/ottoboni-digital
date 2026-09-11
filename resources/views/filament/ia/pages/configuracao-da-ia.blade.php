<x-filament-panels::page>
    @php $cfg = $this->config(); @endphp

    <div class="flex flex-wrap items-center justify-between gap-4">
        {{-- Modelo travado: mostrado para auditoria, não para operação. --}}
        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedLockClosed" class="h-5 w-5 text-gray-400" />
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Modelo em uso</p>
                <p class="font-mono text-sm font-semibold">{{ $cfg->modelo }}</p>
            </div>
            <p class="max-w-xs border-l border-gray-200 pl-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                Não editável por aqui. Trocar o modelo muda custo e comportamento de todas as respostas
                de uma vez — é alteração técnica, feita por quem mantém o sistema.
            </p>
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

    @unless ($this->n8nConfigurado())
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm dark:border-warning-500/30 dark:bg-warning-500/10">
            <p class="font-semibold text-warning-700 dark:text-warning-400">Envio pelo n8n não configurado</p>
            <p class="mt-1 text-warning-700/80 dark:text-warning-400/80">
                A fila funciona e as aprovações ficam registradas, mas nada sai para o Instagram até
                <code>IA_ENVIO_URL</code> e <code>IA_WEBHOOK_SECRET</code> estarem no .env do servidor.
            </p>
        </div>
    @endunless

    <form wire:submit="salvar">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">Salvar</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
