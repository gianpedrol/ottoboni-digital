@php $valor = $getState(); @endphp

@if (blank($valor))
    <p class="text-sm text-gray-500 dark:text-gray-400">—</p>
@else
    @if (is_array($valor) && filled($valor['descricao'] ?? null))
        <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">{{ $valor['descricao'] }}</p>
    @endif

    <pre class="overflow-x-auto rounded-md bg-gray-950 p-3 text-xs leading-relaxed text-gray-100">{{ json_encode($valor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
@endif
