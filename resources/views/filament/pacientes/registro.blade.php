@php
    $registro = $getRecord();
    $tz = \App\Filament\Resources\Patients\FichaDoPaciente::tz();
@endphp

<div>
    @include('filament.pacientes.registro-conteudo', ['registro' => $registro])

    <dl class="mt-6 grid gap-3 border-t border-gray-200 pt-4 text-xs sm:grid-cols-2 dark:border-white/10">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Registrado em</dt>
            <dd>{{ $registro->created_at?->copy()->timezone($tz)->format('d/m/Y H:i') }} · {{ $registro->autor?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Consulta vinculada</dt>
            <dd>{{ $registro->appointment?->inicio?->copy()->timezone($tz)->format('d/m/Y H:i') ?? 'Sem vínculo' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-gray-500 dark:text-gray-400">Assinatura</dt>
            <dd>
                @if ($registro->estaAssinado())
                    Assinado em {{ $registro->assinado_em->copy()->timezone($tz)->format('d/m/Y H:i') }} — somente leitura.
                @else
                    Rascunho: ainda pode ser editado.
                @endif
            </dd>
        </div>
    </dl>
</div>
