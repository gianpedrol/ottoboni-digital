<x-filament-panels::page>
    <form wire:submit="salvar">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Salvar
            </x-filament::button>
        </div>
    </form>

    @if (count($this->mapaDeCampos))
        <x-filament::section>
            <x-slot name="heading">Campos personalizados encontrados no Kommo</x-slot>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1 pr-4">Nome no Kommo</th>
                        <th class="py-1">ID</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->mapaDeCampos as $campo)
                        <tr class="border-t border-gray-100">
                            <td class="py-1 pr-4">{{ $campo['name'] }}</td>
                            <td class="py-1">{{ $campo['id'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Integração n8n (Fase 2)</x-slot>

        <p class="text-sm text-gray-500">
            O webhook do FOLLOWUP EXECUTOR será configurado na Fase 2.
            As variáveis N8N_FOLLOWUP_URL e N8N_WEBHOOK_SECRET ficam no .env do servidor.
        </p>
    </x-filament::section>
</x-filament-panels::page>
