@php
    use App\Enums\StatusAgendamento;
    use App\Enums\TipoAgendamento;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Js;

    // Classes completas aqui (e não montadas por string) para o Tailwind enxergar.
    $coresStatus = [
        'agendado' => 'bg-sky-50 text-sky-950 ring-sky-200 hover:bg-sky-100 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-400/25 dark:hover:bg-sky-500/25',
        'confirmado' => 'bg-emerald-50 text-emerald-950 ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-50 dark:ring-emerald-400/25 dark:hover:bg-emerald-500/25',
        'realizado' => 'bg-gray-100 text-gray-700 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/15',
        'faltou' => 'bg-rose-50 text-rose-950 ring-rose-200 hover:bg-rose-100 dark:bg-rose-500/15 dark:text-rose-50 dark:ring-rose-400/25 dark:hover:bg-rose-500/25',
        'cancelado' => 'bg-gray-50 text-gray-400 line-through ring-gray-200 dark:bg-white/5 dark:text-gray-500 dark:ring-white/10',
        'remarcado' => 'bg-amber-50 text-amber-900 ring-amber-200 opacity-80 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-400/20',
    ];

    $pontosStatus = [
        'agendado' => 'bg-sky-400',
        'confirmado' => 'bg-emerald-500',
        'realizado' => 'bg-gray-400',
        'faltou' => 'bg-rose-500',
        'cancelado' => 'bg-gray-300 dark:bg-gray-600',
        'remarcado' => 'bg-amber-400',
    ];

    $coresMedico = [
        'teal' => ['borda' => 'border-l-teal-700', 'ponto' => 'bg-teal-700', 'texto' => 'text-teal-800'],
        'rose' => ['borda' => 'border-l-primary-400', 'ponto' => 'bg-primary-400', 'texto' => 'text-primary-700'],
        'slate' => ['borda' => 'border-l-slate-500 dark:border-l-slate-400', 'ponto' => 'bg-slate-500', 'texto' => 'text-slate-700 dark:text-slate-300'],
    ];
@endphp

<x-filament-panels::page>
    {{-- Barra de controle --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-filament::icon-button
                :icon="Heroicon::OutlinedChevronLeft"
                color="gray"
                wire:click="anterior"
                :label="$visao === 'dia' ? 'Dia anterior' : 'Semana anterior'"
            />
            <x-filament::button color="gray" size="sm" wire:click="hoje">
                Hoje
            </x-filament::button>
            <x-filament::icon-button
                :icon="Heroicon::OutlinedChevronRight"
                color="gray"
                wire:click="proximo"
                :label="$visao === 'dia' ? 'Próximo dia' : 'Próxima semana'"
            />
            <h2 class="ms-2 text-lg font-semibold text-gray-950 dark:text-white">
                {{ $periodo }}
            </h2>
            <x-filament::loading-indicator class="h-5 w-5 text-gray-400" wire:loading />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$visao !== 'dia'"
                    :icon="Heroicon::OutlinedCalendarDays"
                    wire:click="$set('visao', 'semana')"
                >
                    Semana
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$visao === 'dia'"
                    :icon="Heroicon::OutlinedQueueList"
                    wire:click="$set('visao', 'dia')"
                >
                    Dia / recepção
                </x-filament::tabs.item>
            </x-filament::tabs>

            @if (count($opcoesMedico) > 1)
                <x-filament::input.wrapper class="min-w-56">
                    <x-filament::input.select wire:model.live="medico" aria-label="Médico">
                        @foreach ($opcoesMedico as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            @endif

            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <x-filament::input.checkbox wire:model.live="mostrarCancelados" />
                Mostrar cancelados
            </label>
        </div>
    </div>

    @if ($semana !== null)
        {{-- Visão semana --}}
        <div
            class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            wire:loading.class="opacity-60"
        >
            <div @class(['min-w-[56rem]' => ! $semana['ladoALado'], 'min-w-[78rem]' => $semana['ladoALado']])>
                {{-- Cabeçalho dos dias --}}
                <div
                    class="grid border-b border-gray-200 dark:border-white/10"
                    style="grid-template-columns: 4rem repeat(6, minmax(0, 1fr));"
                >
                    <div></div>
                    @foreach ($semana['dias'] as $dia)
                        <button
                            type="button"
                            wire:click="irPara('{{ $dia['data'] }}')"
                            title="Abrir a lista do dia"
                            @class([
                                'group border-l border-gray-200 px-2 pb-2 pt-3 text-center transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5',
                                'bg-primary-50/60 dark:bg-primary-500/10' => $dia['hoje'],
                            ])
                        >
                            <div @class([
                                'text-xs font-medium uppercase tracking-wide',
                                'text-primary-700 dark:text-primary-300' => $dia['hoje'],
                                'text-gray-500 dark:text-gray-400' => ! $dia['hoje'],
                            ])>
                                {{ $dia['semana'] }}
                            </div>
                            <div @class([
                                'mx-auto mt-1 flex h-9 w-9 items-center justify-center rounded-full text-lg font-semibold',
                                'bg-primary-600 text-white' => $dia['hoje'],
                                'text-gray-400 dark:text-gray-500' => $dia['passado'] && ! $dia['hoje'],
                                'text-gray-950 dark:text-white' => ! $dia['passado'] && ! $dia['hoje'],
                            ])>
                                {{ $dia['numero'] }}
                            </div>
                            <div class="mt-1 text-[11px] text-gray-500 group-hover:text-primary-600 dark:text-gray-400">
                                {{ $dia['total'] }} {{ $dia['total'] === 1 ? 'agendamento' : 'agendamentos' }}
                            </div>

                            @if ($semana['ladoALado'])
                                <div class="mt-2 flex gap-1">
                                    @foreach ($dia['colunas'] as $coluna)
                                        <span class="flex flex-1 items-center justify-center gap-1 truncate rounded bg-gray-50 px-1 py-0.5 text-[11px] font-medium dark:bg-white/5 {{ $coresMedico[$coluna['cor']]['texto'] }}">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $coresMedico[$coluna['cor']]['ponto'] }}"></span>
                                            {{ $coluna['apelido'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Grade de horários --}}
                <div class="grid" style="grid-template-columns: 4rem repeat(6, minmax(0, 1fr));">
                    <div class="relative" style="height: {{ $semana['altura'] }}px">
                        @foreach ($semana['slots'] as $slot)
                            @if ($slot['cheia'])
                                <div
                                    class="absolute right-2 text-[11px] font-medium tabular-nums text-gray-500 dark:text-gray-400"
                                    style="top: {{ max(0, $slot['top'] - 7) }}px"
                                >
                                    {{ $slot['hora'] }}
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @foreach ($semana['dias'] as $dia)
                        <div
                            @class([
                                'relative flex border-l border-gray-200 dark:border-white/10',
                                'bg-primary-50/30 dark:bg-primary-500/5' => $dia['hoje'],
                            ])
                            style="height: {{ $semana['altura'] }}px"
                        >
                            {{-- Linhas de fundo --}}
                            @foreach ($semana['slots'] as $slot)
                                <div
                                    @class([
                                        'pointer-events-none absolute inset-x-0 border-t',
                                        'border-gray-200 dark:border-white/10' => $slot['cheia'],
                                        'border-dashed border-gray-100 dark:border-white/5' => ! $slot['cheia'],
                                    ])
                                    style="top: {{ $slot['top'] }}px"
                                ></div>
                            @endforeach

                            @foreach ($dia['colunas'] as $coluna)
                                <div @class([
                                    'relative min-w-0 flex-1',
                                    'border-l border-dashed border-gray-200 dark:border-white/10' => ! $loop->first,
                                ])>
                                    {{-- Horários livres: clique para agendar --}}
                                    @foreach ($semana['slots'] as $slot)
                                        <button
                                            type="button"
                                            wire:click="mountAction('novo', {{ Js::from(['data' => $dia['data'], 'hora' => $slot['hora'], 'medico' => $coluna['medico']->id]) }})"
                                            class="absolute inset-x-0 z-0 flex items-start px-1.5 pt-1 text-[11px] font-medium text-transparent transition hover:bg-primary-50 hover:text-primary-700 focus-visible:bg-primary-50 focus-visible:text-primary-700 focus-visible:outline-none dark:hover:bg-primary-500/10 dark:hover:text-primary-300"
                                            style="top: {{ $slot['top'] }}px; height: {{ $alturaSlot }}px"
                                            title="Agendar {{ $coluna['apelido'] }} às {{ $slot['hora'] }}"
                                        >
                                            + {{ $slot['hora'] }}
                                        </button>
                                    @endforeach

                                    {{-- Agendamentos --}}
                                    @foreach ($coluna['eventos'] as $evento)
                                        <button
                                            type="button"
                                            wire:click="mountAction('detalhe', {{ Js::from(['id' => $evento['id']]) }})"
                                            class="absolute z-10 flex flex-col overflow-hidden rounded-md border-l-4 px-1.5 py-1 text-left text-xs shadow-sm ring-1 transition hover:z-20 hover:shadow-md {{ $coresStatus[$evento['status']->value] }} {{ $coresMedico[$coluna['cor']]['borda'] }}"
                                            style="top: {{ $evento['top'] }}px; height: {{ $evento['altura'] }}px; left: calc({{ $evento['esquerda'] }}% + 2px); width: calc({{ $evento['largura'] }}% - 4px);"
                                            title="{{ $evento['horario'] }} · {{ $evento['paciente'] }} · {{ $evento['tipo']->getLabel() }} · {{ $evento['status']->getLabel() }}"
                                        >
                                            <span class="flex min-w-0 items-center gap-1 font-semibold leading-tight">
                                                <x-filament::icon :icon="$evento['tipo']->getIcon()" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                                <span class="truncate">{{ $evento['paciente'] }}</span>
                                            </span>

                                            @unless ($evento['compacto'])
                                                <span class="mt-0.5 truncate leading-tight opacity-80">
                                                    {{ $evento['horario'] }} · {{ $evento['tipo']->getLabel() }}
                                                </span>

                                                @if ($evento['altura'] >= 80 && $evento['servico'])
                                                    <span class="mt-0.5 truncate leading-tight opacity-70">{{ $evento['servico'] }}</span>
                                                @endif

                                                @if ($evento['altura'] >= 110 && $evento['sala'])
                                                    <span class="mt-0.5 truncate leading-tight opacity-70">{{ $evento['sala'] }}</span>
                                                @endif

                                                @if ($evento['altura'] >= 60 && ! in_array($evento['status'], [StatusAgendamento::Agendado], true))
                                                    <span class="mt-auto flex items-center gap-1 text-[10px] font-medium uppercase tracking-wide opacity-80">
                                                        <span class="h-1.5 w-1.5 rounded-full {{ $pontosStatus[$evento['status']->value] }}"></span>
                                                        {{ $evento['status']->getLabel() }}
                                                    </span>
                                                @endif
                                            @endunless
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach

                            {{-- Agora --}}
                            @if ($dia['hoje'] && $semana['linhaAgora'] !== null)
                                <div
                                    class="pointer-events-none absolute inset-x-0 z-30 border-t-2 border-danger-500"
                                    style="top: {{ $semana['linhaAgora'] }}px"
                                >
                                    <span class="absolute -left-1 -top-[5px] h-2 w-2 rounded-full bg-danger-500"></span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Legenda --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-600 dark:text-gray-400">
            @if ($semana['ladoALado'])
                <div class="flex items-center gap-3">
                    @foreach ($semana['medicos'] as $m)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-1 rounded-sm {{ $coresMedico[$m['cor']]['ponto'] }}"></span>
                            {{ $m['nome'] }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                @foreach (StatusAgendamento::cases() as $status)
                    @if ($mostrarCancelados || ! in_array($status, StatusAgendamento::liberamHorario(), true))
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full {{ $pontosStatus[$status->value] }}"></span>
                            {{ $status->getLabel() }}
                        </span>
                    @endif
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @foreach (TipoAgendamento::cases() as $tipo)
                    <span class="inline-flex items-center gap-1">
                        <x-filament::icon :icon="$tipo->getIcon()" class="h-3.5 w-3.5" />
                        {{ $tipo->getLabel() }}
                    </span>
                @endforeach
            </div>

            <span class="ms-auto">Clique num horário livre para agendar ou num agendamento para ver as ações.</span>
        </div>
    @else
        {{-- Visão dia / recepção --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([
                ['Agendamentos', $resumoDia['agendamentos'], 'text-gray-950 dark:text-white'],
                ['Confirmados', $resumoDia['confirmados'], 'text-emerald-600 dark:text-emerald-400'],
                ['Realizados', $resumoDia['realizados'], 'text-gray-600 dark:text-gray-300'],
                ['Faltas', $resumoDia['faltas'], 'text-rose-600 dark:text-rose-400'],
                ['Cancelados / remarcados', $resumoDia['cancelados'], 'text-amber-600 dark:text-amber-400'],
            ] as [$rotulo, $valor, $cor])
                <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $rotulo }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums {{ $cor }}">{{ $valor }}</div>
                </div>
            @endforeach
        </div>

        {{ $this->table }}
    @endif
</x-filament-panels::page>
