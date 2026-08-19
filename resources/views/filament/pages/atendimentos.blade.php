<x-filament-panels::page>
    @if ($this->dataDe)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Dados de {{ $this->dataDe }} — use "Atualizar agora" para buscar direto do Kommo.
        </p>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
