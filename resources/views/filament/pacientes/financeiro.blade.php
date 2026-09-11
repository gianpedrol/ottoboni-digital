@php
    $ficha = \App\Filament\Resources\Patients\FichaDoPaciente::class;
    $fin = $ficha::financeiro($getRecord());
    $tz = $ficha::tz();
    $cards = [
        ['Total contratado', $fin['total'], 'text-gray-950 dark:text-white'],
        ['Pago', $fin['pago'], 'text-success-600 dark:text-success-400'],
        ['Em aberto', $fin['aberto'], 'text-warning-600 dark:text-warning-400'],
        ['Em atraso', $fin['atrasado'], 'text-danger-600 dark:text-danger-400'],
    ];
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as [$rotulo, $valor, $cor])
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $rotulo }}</p>
                <p class="mt-1 text-xl font-semibold {{ $cor }}">{{ $ficha::moeda($valor) }}</p>
            </div>
        @endforeach
    </div>

    @forelse ($fin['recebiveis'] as $recebivel)
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div>
                    <p class="font-medium text-gray-950 dark:text-white">{{ $recebivel->descricao }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $ficha::rotulo($recebivel->forma_pagamento) }}
                        @if ($recebivel->doctor)
                            · {{ $recebivel->doctor->nome }}
                        @endif
                        · lançado em {{ $recebivel->created_at?->copy()->timezone($tz)->format('d/m/Y') }}
                    </p>
                </div>
                <p class="font-semibold text-gray-950 dark:text-white">{{ $ficha::moeda($recebivel->valor_total) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2 text-start font-medium">Parcela</th>
                            <th class="px-4 py-2 text-start font-medium">Vencimento</th>
                            <th class="px-4 py-2 text-end font-medium">Valor</th>
                            <th class="px-4 py-2 text-start font-medium">Pago em</th>
                            <th class="px-4 py-2 text-start font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($recebivel->installments as $parcela)
                            @php $status = $ficha::statusParcela($parcela); @endphp
                            <tr>
                                <td class="px-4 py-2">{{ $parcela->numero }}/{{ $recebivel->installments->count() }}</td>
                                <td class="whitespace-nowrap px-4 py-2">{{ $parcela->vencimento?->format('d/m/Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-end">{{ $ficha::moeda($parcela->valor) }}</td>
                                <td class="whitespace-nowrap px-4 py-2">{{ $parcela->pago_em?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <x-filament::badge :color="$ficha::corStatusParcela($status)" class="w-fit">
                                        {{ $ficha::rotulo($status) }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-gray-500 dark:text-gray-400">Sem parcelas lançadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum recebível para este paciente.</p>
    @endforelse

    <p class="text-xs text-gray-500 dark:text-gray-400">Somente leitura. Lançamentos e baixas ficam no módulo Financeiro.</p>
</div>
