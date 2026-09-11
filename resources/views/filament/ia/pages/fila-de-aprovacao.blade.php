<x-filament-panels::page>
    @php
        $item = $this->item();
        $resumo = $this->resumo();
        $fila = $this->fila();
    @endphp

    {{-- Atalhos de teclado: com a fila cheia, decidir com a mão no teclado
         muda o dia de quem revisa. Ignora quando o foco está num campo. --}}
    <div
        x-data="{
            atalho(e) {
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
                if (e.metaKey || e.ctrlKey || e.altKey) return;
                const tecla = e.key.toLowerCase();
                if (tecla === 'a') { e.preventDefault(); $refs.btnAprovar?.click(); }
                if (tecla === 's') { e.preventDefault(); $refs.btnPular?.click(); }
                if (tecla === 'e') { e.preventDefault(); $refs.campoDm?.focus(); }
            }
        }"
        x-on:keydown.window="atalho($event)"
        class="space-y-6"
    >
        {{-- Resumo + seletor de agente --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge :color="$resumo['pendentes'] > 0 ? 'warning' : 'success'" size="lg">
                    {{ $resumo['pendentes'] }} aguardando revisão
                </x-filament::badge>

                @if ($resumo['nao_sei'] > 0)
                    <x-filament::badge color="danger" size="lg">
                        {{ $resumo['nao_sei'] }} que a agente não soube responder
                    </x-filament::badge>
                @endif

                @if ($resumo['atrasados'] > 0)
                    <x-filament::badge color="gray" size="lg">
                        {{ $resumo['atrasados'] }} passando do prazo
                    </x-filament::badge>
                @endif
            </div>

            @if (count($this->agentes()) > 1)
                <select
                    wire:model.live="doctorId"
                    class="fi-input block w-64 rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                >
                    @foreach ($this->agentes() as $id => $rotulo)
                        <option value="{{ $id }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        @if ($item === null)
            <x-filament::section>
                <div class="py-10 text-center">
                    <p class="text-lg font-medium">Fila vazia.</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Nenhuma resposta aguardando revisão
                        @if (count($this->pulados) > 0)
                            — você pulou {{ count($this->pulados) }} nesta sessão.
                            <button wire:click="$set('pulados', [])" class="font-medium text-primary-600 underline">
                                rever os pulados
                            </button>
                        @endif
                    </p>
                </div>
            </x-filament::section>
        @else
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {{-- ---------- Coluna 1: o contexto ---------- --}}
                <div class="space-y-4">
                    <x-filament::section :heading="'Por que está aqui'" compact>
                        <div class="flex flex-wrap gap-2">
                            <x-filament::badge :color="$item->motivo_fila->getColor()">
                                {{ $item->motivo_fila->getLabel() }}
                            </x-filament::badge>
                            <x-filament::badge :color="$item->intent->getColor()">
                                {{ $item->intent->getLabel() }}
                            </x-filament::badge>
                            <x-filament::badge color="gray">
                                {{ $item->canal->getLabel() }}
                            </x-filament::badge>
                            @if ($item->estaAtrasado())
                                <x-filament::badge color="danger">Passou do prazo</x-filament::badge>
                            @endif
                        </div>

                        <dl class="mt-4 space-y-1 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Na fila há</dt>
                                <dd class="font-medium">{{ $item->created_at?->diffForHumans(null, true) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Modelo</dt>
                                <dd class="inline-flex items-center gap-1 font-medium">
                                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedLockClosed" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $item->modelo ?? '—' }}
                                </dd>
                            </div>
                            @if ($item->confianca !== null)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500 dark:text-gray-400">Confiança</dt>
                                    <dd class="font-medium">{{ number_format($item->confianca * 100, 0) }}%</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Instruções</dt>
                                <dd class="font-medium">
                                    {{ $item->promptVersion ? 'v' . $item->promptVersion->versao : '—' }}
                                </dd>
                            </div>
                        </dl>
                    </x-filament::section>

                    <x-filament::section :heading="'O post'" compact collapsible>
                        @if ($item->post_media_url)
                            <img
                                src="{{ $item->post_media_url }}"
                                alt="Imagem do post"
                                class="mb-3 w-full rounded-lg object-cover"
                                loading="lazy"
                            >
                        @endif

                        <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">
                            {{ \Illuminate\Support\Str::limit($item->post_caption ?? 'Sem legenda registrada.', 400) }}
                        </p>

                        @if ($item->post_permalink)
                            <a
                                href="{{ $item->post_permalink }}"
                                target="_blank"
                                rel="noopener"
                                class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline"
                            >
                                Abrir no Instagram
                            </a>
                        @endif
                    </x-filament::section>

                    @if (filled($item->historico))
                        <x-filament::section :heading="'Conversa anterior'" compact collapsible collapsed>
                            <div class="space-y-2 text-sm">
                                @foreach ($item->historico as $msg)
                                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                                        <span class="text-xs font-medium uppercase text-gray-400">
                                            {{ data_get($msg, 'role', data_get($msg, 'de', '—')) }}
                                        </span>
                                        <p class="whitespace-pre-line">{{ data_get($msg, 'content', data_get($msg, 'texto', '')) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif
                </div>

                {{-- ---------- Coluna 2 e 3: a decisão ---------- --}}
                <div class="space-y-4 xl:col-span-2">
                    <x-filament::section compact>
                        <x-slot name="heading">
                            {{ $item->ig_username ? '@' . $item->ig_username : 'Paciente' }} escreveu
                        </x-slot>

                        <blockquote class="border-l-4 border-primary-500 pl-4 text-base whitespace-pre-line">
                            {{ $item->textoDaPessoa() ?: '(sem texto)' }}
                        </blockquote>
                    </x-filament::section>

                    @if ($item->intent === \App\Enums\IaIntent::NaoSei)
                        <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm dark:border-danger-500/30 dark:bg-danger-500/10">
                            <p class="font-semibold text-danger-700 dark:text-danger-400">
                                A agente não encontrou resposta na base.
                            </p>
                            <p class="mt-1 text-danger-700/80 dark:text-danger-400/80">
                                O que você escrever aqui vira card validado na base de conhecimento — na próxima
                                vez que perguntarem isso, ela responde sozinha.
                            </p>
                        </div>
                    @endif

                    <x-filament::section :heading="'Resposta que vai sair'" compact>
                        <x-slot name="description">
                            Edite à vontade. Campo em branco significa que aquele canal não recebe nada.
                        </x-slot>

                        <div class="space-y-4">
                            @if ($item->canal === \App\Enums\IaCanal::Comentario)
                                <div>
                                    <label for="campo-comentario" class="mb-1 block text-sm font-medium">
                                        Resposta pública no comentário
                                    </label>
                                    <textarea
                                        id="campo-comentario"
                                        wire:model="comentario"
                                        rows="2"
                                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                        placeholder="Deixe vazio para não responder publicamente"
                                    ></textarea>
                                    @if (filled($item->rascunho_comentario))
                                        <p class="mt-1 text-xs text-gray-400">
                                            Rascunho da agente: "{{ $item->rascunho_comentario }}"
                                        </p>
                                    @endif
                                </div>
                            @endif

                            <div>
                                <label for="campo-dm" class="mb-1 block text-sm font-medium">
                                    Direct
                                </label>
                                <textarea
                                    id="campo-dm"
                                    x-ref="campoDm"
                                    wire:model="dm"
                                    rows="7"
                                    class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                    placeholder="Deixe vazio para não mandar direct"
                                ></textarea>
                            </div>

                            <div>
                                <label for="campo-obs" class="mb-1 block text-sm font-medium">
                                    Observação para o histórico <span class="font-normal text-gray-400">(opcional)</span>
                                </label>
                                <input
                                    id="campo-obs"
                                    type="text"
                                    wire:model="observacao"
                                    class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                    placeholder="Ex.: corrigi o programa, ela ofereceu o Raiz no lugar do Pleno"
                                >
                            </div>
                        </div>
                    </x-filament::section>

                    {{-- Responsabilidade: a regra também é exigida no serviço,
                         então não existe caminho que passe por fora daqui. --}}
                    <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                        <label class="flex cursor-pointer items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                wire:model="aceite"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600"
                            >
                            <span>
                                <strong>Assumo a responsabilidade por esta resposta.</strong>
                                Ela será enviada em nome de {{ $item->doctor?->nome ?? 'a clínica' }} exatamente
                                como está escrito acima, e a autoria da revisão fica registrada com meu nome.
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-filament::button
                            x-ref="btnAprovar"
                            wire:click="aprovar"
                            wire:loading.attr="disabled"
                            :disabled="! $this->aceite"
                            size="lg"
                            icon="heroicon-o-paper-airplane"
                        >
                            Aprovar e enviar
                            <span class="ml-1 opacity-60">(A)</span>
                        </x-filament::button>

                        <x-filament::button
                            x-ref="btnPular"
                            wire:click="pular"
                            color="gray"
                            size="lg"
                            icon="heroicon-o-arrow-right-circle"
                        >
                            Pular
                            <span class="ml-1 opacity-60">(S)</span>
                        </x-filament::button>

                        <x-filament::modal id="rejeitar" width="lg">
                            <x-slot name="trigger">
                                <x-filament::button color="danger" size="lg" icon="heroicon-o-no-symbol">
                                    Rejeitar
                                </x-filament::button>
                            </x-slot>

                            <x-slot name="heading">Rejeitar e ficar em silêncio</x-slot>

                            <x-slot name="description">
                                Nada será enviado. O item conta como erro na acurácia e o motivo entra
                                no histórico de treinamento.
                            </x-slot>

                            <textarea
                                wire:model="motivoRejeicao"
                                rows="3"
                                class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                placeholder="Por que esta resposta não deveria existir?"
                            ></textarea>

                            <x-slot name="footer">
                                <x-filament::button wire:click="rejeitar" color="danger">
                                    Confirmar rejeição
                                </x-filament::button>
                            </x-slot>
                        </x-filament::modal>
                    </div>
                </div>
            </div>

            {{-- ---------- Resto da fila ---------- --}}
            @if ($fila->count() > 1)
                <x-filament::section :heading="'Resto da fila'" compact collapsible collapsed>
                    <div class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($fila as $outro)
                            @continue($outro->id === $item->id)
                            <button
                                wire:click="abrir({{ $outro->id }})"
                                class="flex w-full items-center gap-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <x-filament::badge :color="$outro->intent->getColor()" size="sm">
                                    {{ $outro->intent->getLabel() }}
                                </x-filament::badge>
                                <span class="flex-1 truncate">
                                    {{ \Illuminate\Support\Str::limit($outro->textoDaPessoa(), 90) ?: '(sem texto)' }}
                                </span>
                                <span class="shrink-0 text-xs text-gray-400">
                                    {{ $outro->created_at?->diffForHumans(null, true) }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
