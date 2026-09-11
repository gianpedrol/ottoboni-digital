<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-700 dark:text-gray-200">
            Cada etapa do painel aponta para um ou mais <code>status_id</code> em cada pipeline do Kommo.
            As automações e os relatórios usam só esses IDs, nunca o nome da etapa, porque os nomes mudam de médico para médico.
        </p>
        <p class="mt-2 text-sm font-medium text-gray-500 dark:text-gray-400">
            Somente leitura. Após a reestruturação do Kommo este mapa vira editável.
        </p>
    </x-filament::section>

    @if ($this->aviso)
        <div class="rounded-lg bg-warning-50 p-4 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30">
            {{ $this->aviso }}
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white">Etapa do painel</th>
                    @foreach ($this->pipelines as $pipeline)
                        <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white">
                            {{ $pipeline['nome'] }}
                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">pipeline {{ $pipeline['id'] }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($this->linhas as $linha)
                    <tr>
                        <td class="px-4 py-3 align-top">
                            <x-filament::badge :color="$linha['cor']">{{ $linha['etapa'] }}</x-filament::badge>
                        </td>
                        @foreach ($this->pipelines as $pipeline)
                            <td class="px-4 py-3 align-top">
                                @forelse ($linha['celulas'][$pipeline['id']] ?? [] as $status)
                                    <div class="flex flex-wrap items-baseline gap-2">
                                        <code class="text-xs text-gray-500 dark:text-gray-400">{{ $status['id'] }}</code>
                                        @if ($this->comNomes)
                                            @if ($status['nome'] !== null)
                                                <span class="text-gray-950 dark:text-white">{{ $status['nome'] }}</span>
                                            @else
                                                <span class="text-xs text-danger-600 dark:text-danger-400">não encontrado no Kommo</span>
                                            @endif
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-xs text-gray-400 dark:text-gray-500">— não existe neste pipeline</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-400 dark:text-gray-500">
        Fonte: config/etapas.php. 142 (Contratado, a antiga “Venda ganha”) e 143 (Perdido) valem para todo pipeline.
    </p>
</x-filament-panels::page>
