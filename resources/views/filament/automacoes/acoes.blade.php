@php
    $formatar = fn ($valor) => json_encode($valor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

@if (empty($acoes))
    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma ação.</p>
@else
    <ol class="space-y-3">
        @foreach ($acoes as $i => $acao)
            @php $aplicavel = (bool) ($acao['aplicavel'] ?? true); @endphp
            <li @class([
                'rounded-lg border p-4',
                'border-gray-200 dark:border-white/10' => $aplicavel,
                'border-dashed border-gray-300 opacity-75 dark:border-white/10' => ! $aplicavel,
            ])>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ $i + 1 }}
                    </span>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $acao['descricao'] ?? '' }}</span>
                    @unless ($aplicavel)
                        <x-filament::badge color="gray">ignorada</x-filament::badge>
                    @endunless
                </div>

                @if (filled($acao['observacao'] ?? null))
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $acao['observacao'] }}</p>
                @endif

                @if ($aplicavel && filled($acao['metodo'] ?? null))
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <x-filament::badge :color="$acao['metodo'] === 'PAINEL' ? 'gray' : 'info'">{{ $acao['metodo'] }}</x-filament::badge>
                        <code class="text-gray-700 dark:text-gray-300">{{ $acao['endpoint'] ?? '' }}</code>
                    </div>

                    @if (($acao['payload'] ?? null) !== null)
                        <pre class="mt-2 overflow-x-auto rounded-md bg-gray-950 p-3 text-xs leading-relaxed text-gray-100">{{ $formatar($acao['payload']) }}</pre>
                    @endif
                @endif
            </li>
        @endforeach
    </ol>
@endif
