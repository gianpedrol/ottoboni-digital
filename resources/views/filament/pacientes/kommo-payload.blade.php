@php
    $ficha = \App\Filament\Resources\Patients\FichaDoPaciente::class;
    $payload = $getRecord()->kommo_sync_payload;
@endphp

@if (blank($payload))
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Nenhuma requisição montada ainda. Use "Simular envio ao Kommo" no topo da página.
    </p>
@else
    <div class="space-y-5">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            @if (filled($payload['gerado_em'] ?? null))
                Montado em {{ \Illuminate\Support\Carbon::parse($payload['gerado_em'])->timezone($ficha::tz())->format('d/m/Y H:i') }}
            @endif
            · modo <strong>{{ $payload['modo'] ?? '—' }}</strong>
        </p>

        @foreach ($payload['requisicoes'] ?? [] as $indice => $requisicao)
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Passo {{ $indice + 1 }}</span>
                    <x-filament::badge :color="($requisicao['metodo'] ?? '') === 'POST' ? 'success' : 'info'">
                        {{ $requisicao['metodo'] ?? '' }}
                    </x-filament::badge>
                    <code class="text-sm text-gray-950 dark:text-white">{{ $requisicao['endpoint'] ?? '' }}</code>
                </div>
                <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100"><code>{{ $ficha::json($requisicao['corpo'] ?? []) }}</code></pre>
            </div>
        @endforeach

        @if (! empty($payload['observacoes']))
            <ul class="list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($payload['observacoes'] as $observacao)
                    <li>{{ $observacao }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
