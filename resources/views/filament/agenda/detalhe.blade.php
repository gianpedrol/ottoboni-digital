@php
    /** @var \App\Models\Appointment $agendamento */
    $inicio = $agendamento->inicioLocal()->locale('pt_BR');
    $telefone = \App\Support\Telefone::paraMascara($agendamento->patient?->telefone);
    $servico = $agendamento->service;
@endphp

<div class="space-y-4 text-sm">
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$agendamento->status->getColor()" :icon="$agendamento->status->getIcon()">
            {{ $agendamento->status->getLabel() }}
        </x-filament::badge>
        <x-filament::badge :color="$agendamento->tipo->getColor()" :icon="$agendamento->tipo->getIcon()">
            {{ $agendamento->tipo->getLabel() }}
        </x-filament::badge>
    </div>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Quando</dt>
            <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">
                {{ ucfirst($inicio->translatedFormat('l, d/m/Y')) }}<br>
                {{ $agendamento->faixaHorario() }}
                <span class="font-normal text-gray-500 dark:text-gray-400">({{ $agendamento->duracaoMin() }} min)</span>
            </dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Médico</dt>
            <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $agendamento->doctor?->nome }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Serviço</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">
                @if ($servico)
                    {{ $servico->nome }}
                    @if ((float) $servico->valor > 0)
                        <span class="text-gray-500 dark:text-gray-400">· {{ \Illuminate\Support\Number::currency((float) $servico->valor, in: 'BRL', locale: 'pt_BR') }}</span>
                    @endif
                @else
                    —
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Sala</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $agendamento->sala ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Telefone</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $telefone ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Kommo</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">
                @if ($agendamento->kommo_lead_id)
                    Lead #{{ $agendamento->kommo_lead_id }}
                @else
                    <span class="text-gray-500 dark:text-gray-400">sem lead vinculado</span>
                @endif
            </dd>
        </div>
    </dl>

    @if (filled($agendamento->observacoes))
        <div class="rounded-lg bg-gray-50 p-3 text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
            {{ $agendamento->observacoes }}
        </div>
    @endif

    @if ($agendamento->kommo_lead_id && $agendamento->status->emAberto())
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Chegou/realizado e Faltou preenchem o campo Comparecimento do lead no Kommo, o que dispara as
            automações A3 e A5. No protótipo isso é só simulado e mostrado na tela.
        </p>
    @endif
</div>
