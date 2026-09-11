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
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                <span class="inline-flex items-center gap-2">
                    <x-filament::icon :icon="Heroicon::OutlinedLink" class="h-4 w-4" />
                    Ligação com o n8n e com o cron
                </span>
            </x-slot>
            <x-slot name="description">
                Copie daqui, não digite. O segredo é o mesmo nas duas pontas: no n8n ele vai no nó CONFIG do fluxo
                da Luna v2 e no nó CONFIG ENVIO do fluxo "IA ENVIO APROVADO".
            </x-slot>

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">PAINEL_URL (no n8n)</dt>
                    <dd class="mt-1 select-all break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900">{{ $lig['painel_url'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        IA_WEBHOOK_SECRET (no n8n)
                        <span class="font-normal normal-case text-gray-400">— {{ $lig['segredo_origem'] }}</span>
                    </dt>
                    <dd class="mt-1 select-all break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900">{{ $lig['segredo'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">URL do cron (na hospedagem, a cada minuto)</dt>
                    <dd class="mt-1 select-all break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900">{{ $lig['cron_url'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Webhook de envio (IA_ENVIO_URL)
                        @if ($lig['envio_ok'])
                            <span class="font-normal normal-case text-success-600">— configurado</span>
                        @else
                            <span class="font-normal normal-case text-danger-600">— falta no .env</span>
                        @endif
                    </dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900">{{ $lig['envio_url'] ?: '—' }}</dd>
                </div>
            </dl>
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
