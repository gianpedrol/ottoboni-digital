<x-filament-panels::page>
    @if ($this->erro)
        <x-filament::section>
            <p class="text-danger-600">{{ $this->erro }}</p>
        </x-filament::section>
    @elseif ($this->lead)
        <div class="grid gap-6 lg:grid-cols-3">
            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">Dados do atendimento</x-slot>

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="font-medium text-gray-500">Médico</dt>
                        <dd>{{ $this->medico ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Etapa</dt>
                        <dd>{{ $this->etapa ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Origem</dt>
                        <dd>{{ $this->lead['origem'] ?? 'Não informado' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Temperatura</dt>
                        <dd>{{ $this->lead['temperatura'] ?? 'Não informado' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Procedimento de interesse</dt>
                        <dd>{{ $this->lead['procedimento'] ?? 'Não informado' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Urgência</dt>
                        <dd>{{ $this->lead['urgencia'] ?? 'Não informado' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Score da IA</dt>
                        <dd>{{ $this->lead['score'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Instagram</dt>
                        <dd>{{ $this->lead['instagram'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Responsável</dt>
                        <dd>{{ $this->responsavel ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Criado em</dt>
                        <dd>{{ $this->lead['criado_em'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Gerado pela agente</dt>
                        <dd>{{ $this->lead['gerado_pela_agente'] ? 'Sim' : 'Não' }}</dd>
                    </div>
                    @if ($this->lead['loss_reason'])
                        <div>
                            <dt class="font-medium text-gray-500">Motivo da perda</dt>
                            <dd>{{ $this->lead['loss_reason'] }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4">
                    <x-filament::button
                        tag="a"
                        href="{{ $this->lead['kommo_url'] }}"
                        target="_blank"
                        icon="heroicon-o-arrow-top-right-on-square"
                        color="gray"
                    >
                        Abrir no Kommo
                    </x-filament::button>
                </div>
            </x-filament::section>

            <div class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">Contato</x-slot>

                    @forelse ($this->contatos as $contato)
                        <div class="text-sm">
                            <p class="font-medium">{{ $contato['nome'] }}</p>
                            @if ($contato['telefone'])
                                <p>{{ $contato['telefone'] }}</p>
                            @endif
                            @if ($contato['email'])
                                <p>{{ $contato['email'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Sem contato vinculado.</p>
                    @endforelse
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Tarefas abertas</x-slot>

                    @forelse ($this->tarefas as $tarefa)
                        <div class="text-sm">
                            <p>{{ $tarefa['texto'] }}</p>
                            @if ($tarefa['prazo'])
                                <p class="text-gray-500">até {{ $tarefa['prazo'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhuma tarefa aberta.</p>
                    @endforelse
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Follow-ups</x-slot>

                    @forelse ($this->followups as $followup)
                        <div class="border-b border-gray-100 pb-2 text-sm last:border-0">
                            <p class="font-medium">{{ $followup['plano'] ?? 'Régua' }} — {{ $followup['status'] }}</p>
                            <p class="text-gray-500">
                                {{ $followup['agendado_para'] }}
                                @if ($followup['canal'])
                                    · {{ $followup['canal'] }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum follow-up para este lead (Fase 2).</p>
                    @endforelse
                </x-filament::section>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Histórico de notas</x-slot>

            @if (! $this->notasCarregadas)
                <x-filament::button wire:click="carregarNotas" color="gray" icon="heroicon-o-clock">
                    Carregar histórico
                </x-filament::button>
            @else
                @forelse ($this->notas as $nota)
                    <div class="border-b border-gray-100 py-2 text-sm last:border-0">
                        <p class="text-gray-500">
                            {{ $nota['quando'] }}
                            · {{ $nota['humana'] ? 'equipe' : $nota['tipo'] }}
                        </p>
                        <p class="whitespace-pre-line">{{ $nota['texto'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nenhuma nota registrada.</p>
                @endforelse
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
