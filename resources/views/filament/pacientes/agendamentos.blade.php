@php
    $ficha = \App\Filament\Resources\Patients\FichaDoPaciente::class;
    $agendamentos = $ficha::agendamentos($getRecord(), $somenteFuturos ?? false);
    $tz = $ficha::tz();
@endphp

@if ($agendamentos->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $vazio ?? 'Nenhum agendamento.' }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-2 pe-4 text-start font-medium">Data</th>
                    <th class="py-2 pe-4 text-start font-medium">Tipo</th>
                    <th class="py-2 pe-4 text-start font-medium">Serviço</th>
                    <th class="py-2 pe-4 text-start font-medium">Médico</th>
                    <th class="py-2 pe-4 text-start font-medium">Status</th>
                    <th class="py-2 text-start font-medium">Sala</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($agendamentos as $agendamento)
                    <tr>
                        <td class="whitespace-nowrap py-2 pe-4 font-medium text-gray-950 dark:text-white">
                            {{ $agendamento->inicio?->copy()->timezone($tz)->format('d/m/Y H:i') }}
                        </td>
                        <td class="py-2 pe-4">{{ $ficha::rotulo($agendamento->tipo) }}</td>
                        <td class="py-2 pe-4">{{ $agendamento->service?->nome ?? '—' }}</td>
                        <td class="py-2 pe-4">{{ $agendamento->doctor?->nome ?? '—' }}</td>
                        <td class="py-2 pe-4">
                            <x-filament::badge :color="$ficha::corStatusAgenda($agendamento->status)" class="w-fit">
                                {{ $ficha::rotulo($agendamento->status) }}
                            </x-filament::badge>
                        </td>
                        <td class="py-2">{{ $agendamento->sala ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
