{{--
    Seletor de agente comum às telas da IA. Some quando o usuário só pode
    treinar uma — o escopo em si é garantido no AgenteSelecionado.
--}}
@if (count($agentes) > 1)
    <x-filament::input.wrapper prefix="Agente" class="w-full sm:w-auto sm:min-w-72">
        <x-filament::input.select wire:model.live="doctorId" aria-label="Agente">
            @foreach ($agentes as $id => $rotulo)
                <option value="{{ $id }}">{{ $rotulo }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
@endif
