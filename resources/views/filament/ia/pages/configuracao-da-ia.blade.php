@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    @php $cfg = $this->config(); @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        {{-- Modelo travado: mostrado para auditoria, não para operação. --}}
        <div class="flex max-w-xl items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3">
            <x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Modelo em uso</p>
                <p class="font-mono text-sm font-semibold text-gray-950">{{ $cfg->modelo }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    Não editável por aqui. Trocar o modelo muda custo e comportamento de todas as respostas
                    de uma vez — é alteração técnica, feita por quem mantém o sistema.
                </p>
            </div>
        </div>

        @include('filament.ia.partials.seletor-agente', ['agentes' => $this->agentes()])
    </div>

    @if ($this->ligacoes() !== null)
        @php $lig = $this->ligacoes(); @endphp
        {{-- Só admin vê: são os valores que ligam o painel ao n8n e ao cron da hospedagem. --}}
        <x-filament::section collapsible>
            <x-slot name="heading">
                <span class="inline-flex items-center gap-2">
                    <x-filament::icon :icon="Heroicon::OutlinedLink" class="h-4 w-4" />
                    Ligar o painel ao n8n e ao cron (passo a passo)
                </span>
            </x-slot>
            <x-slot name="description">
                Só existe UM segredo para copiar. Ele vai em dois nós do n8n, e a URL do cron vai na hospedagem.
                Copie daqui (clique no valor: ele seleciona inteiro), não digite.
            </x-slot>

            <ol class="space-y-5 text-sm">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">1</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-950">Copie o segredo</p>
                        <p class="text-gray-500">
                            É o <code class="rounded bg-gray-100 px-1 font-mono text-xs">IA_WEBHOOK_SECRET</code>
                            ({{ $lig['segredo_origem'] }}).
                        </p>
                        <p class="mt-1 select-all break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900">{{ $lig['segredo'] }}</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">2</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-950">Cole no fluxo da Luna v2</p>
                        <p class="text-gray-500">
                            n8n › workflow <strong>LUNA - DRA VANESSA v2 (PAINEL)</strong> › abra o nó
                            <strong>Extrai Comentario Meta</strong> › no começo do código, troque
                            <code class="rounded bg-gray-100 px-1 font-mono text-xs">COLE_AQUI_O_IA_WEBHOOK_SECRET</code>
                            pelo segredo (mantenha as aspas) › Save.
                            A <code class="rounded bg-gray-100 px-1 font-mono text-xs">PAINEL_URL</code> logo acima já está preenchida com
                            <span class="font-mono text-xs">{{ $lig['painel_url'] }}</span>.
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">3</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-950">Cole no fluxo de envio e ative-o</p>
                        <p class="text-gray-500">
                            n8n › workflow <strong>IA ENVIO APROVADO</strong> › nó <strong>CONFIG ENVIO</strong> › troque o mesmo
                            <code class="rounded bg-gray-100 px-1 font-mono text-xs">COLE_AQUI_O_IA_WEBHOOK_SECRET</code>
                            › Save › ligue a chave <strong>Inactive → Active</strong> no topo.
                            @if ($lig['envio_ok'])
                                <span class="text-success-600">O painel já aponta para ele:</span>
                            @else
                                <span class="text-danger-600">Falta IA_ENVIO_URL no .env do painel:</span>
                            @endif
                            <span class="font-mono text-xs">{{ $lig['envio_url'] ?: '—' }}</span>
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">4</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-950">Agende o cron na hospedagem</p>
                        <p class="text-gray-500">
                            Painel da KingHost › Agendamento de tarefas › todos os campos em "Todos" (a cada minuto) › em
                            "Arquivo do script" a URL abaixo. É ela que dispara o vigia da fila (avisos de pendência) e os follow-ups.
                        </p>
                        <p class="mt-1 select-all break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900">{{ $lig['cron_url'] }}</p>
                    </div>
                </li>
            </ol>
        </x-filament::section>
    @endif

    @unless ($this->n8nConfigurado())
        <div role="alert" class="flex gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm">
            <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="h-5 w-5 shrink-0 text-warning-600" />
            <div>
                <p class="font-semibold text-warning-800">Envio pelo n8n não configurado</p>
                <p class="mt-1 text-warning-700">
                    A fila funciona e as aprovações ficam registradas, mas nada sai para o Instagram até
                    <code class="rounded bg-warning-100 px-1 font-mono text-xs">IA_ENVIO_URL</code>
                    apontar para o webhook "IA ENVIO APROVADO" e a
                    <code class="rounded bg-warning-100 px-1 font-mono text-xs">APP_KEY</code> existir no .env.
                </p>
            </div>
        </div>
    @endunless

    <form wire:submit="salvar">
        {{ $this->form }}

        {{-- Barra fixa: o formulário é longo e o botão não pode sumir no fim da página. --}}
        <div class="sticky bottom-0 z-10 mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200 bg-white/90 px-4 py-3 backdrop-blur sm:rounded-xl sm:border">
            <x-filament::button type="submit" :icon="Heroicon::OutlinedCheck" wire:target="salvar">
                Salvar configuração
            </x-filament::button>
            <span class="text-sm text-gray-500">Vale a partir da próxima resposta da agente.</span>
        </div>
    </form>
</x-filament-panels::page>
