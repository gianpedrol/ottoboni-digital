@php
    use App\Enums\IaCanal;
    use App\Enums\IaIntent;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Str;

    // Classes completas aqui (e não montadas por string) para o Tailwind enxergar.
    $pontos = [
        'success' => 'bg-success-500',
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        'gray' => 'bg-gray-400',
    ];

    $sugestoesDeRejeicao = [
        'Não precisava de resposta (elogio, marcação, emoji)',
        'Informação errada',
        'Assunto para a equipe tratar direto',
    ];
@endphp

<x-filament-panels::page>
    @php
        $item = $this->item();
        $resumo = $this->resumo();
        $fila = $this->fila();
    @endphp

    {{-- Atalhos de teclado: com a fila cheia, decidir com a mão no teclado
         muda o dia de quem revisa. Ignora quando o foco está num campo ou num modal. --}}
    <div
        x-data="{
            lembrandoAceite: false,
            lembrarAceite() {
                this.lembrandoAceite = true;
                this.$refs.aceite?.focus();
                setTimeout(() => this.lembrandoAceite = false, 1600);
            },
            aprovar() {
                this.$wire.aceite ? this.$wire.aprovar() : this.lembrarAceite();
            },
            atalho(e) {
                if (e.target.closest('input, textarea, select, .fi-modal')) return;
                if (e.metaKey || e.ctrlKey || e.altKey || e.repeat) return;
                if (! this.$refs.btnAprovar) return;
                const tecla = e.key.toLowerCase();
                if (tecla === 'a') { e.preventDefault(); this.aprovar(); }
                if (tecla === 'r') { e.preventDefault(); this.$dispatch('open-modal', { id: 'rejeitar' }); }
                if (tecla === 's') { e.preventDefault(); this.$wire.pular(); }
                if (tecla === 'e') { e.preventDefault(); this.$refs.campoDm?.focus(); }
            },
        }"
        x-on:keydown.window="atalho($event)"
        class="space-y-6"
    >
        {{-- ---------- Resumo + seletor de agente ---------- --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge
                    :color="$resumo['pendentes'] > 0 ? 'warning' : 'success'"
                    size="lg"
                    :icon="Heroicon::OutlinedInboxStack"
                >
                    {{ $resumo['pendentes'] }} aguardando revisão
                </x-filament::badge>

                @if ($resumo['nao_sei'] > 0)
                    <x-filament::badge color="danger" size="lg" :icon="Heroicon::OutlinedQuestionMarkCircle">
                        {{ $resumo['nao_sei'] }} que a agente não soube responder
                    </x-filament::badge>
                @endif

                @if ($resumo['atrasados'] > 0)
                    <x-filament::badge color="danger" size="lg" :icon="Heroicon::OutlinedClock">
                        {{ $resumo['atrasados'] }} passando do prazo
                    </x-filament::badge>
                @endif

                @if ($this->revisadosNaSessao > 0)
                    <span class="inline-flex items-center gap-1 ps-1 text-sm text-gray-500">
                        <x-filament::icon :icon="Heroicon::OutlinedCheckCircle" class="h-4 w-4 text-success-500" />
                        você revisou {{ $this->revisadosNaSessao }} nesta sessão
                    </span>
                @endif
            </div>

            @include('filament.ia.partials.seletor-agente', ['agentes' => $this->agentes()])
        </div>

        @if ($item === null)
            <x-filament::empty-state
                :icon="Heroicon::OutlinedCheckBadge"
                icon-color="success"
                heading="Fila zerada"
            >
                <x-slot name="description">
                    @if (count($this->pulados) > 0)
                        Nada novo aguardando revisão — você pulou {{ count($this->pulados) }}
                        {{ count($this->pulados) === 1 ? 'item' : 'itens' }} nesta sessão.
                    @else
                        Nenhuma resposta aguardando revisão. Quando a agente precisar de alguém,
                        o item aparece aqui e no número ao lado do menu.
                    @endif
                </x-slot>

                @if (count($this->pulados) > 0)
                    <x-slot name="footer">
                        <x-filament::button color="gray" wire:click="reverPulados" :icon="Heroicon::OutlinedArrowPath">
                            Rever os pulados
                        </x-filament::button>
                    </x-slot>
                @endif
            </x-filament::empty-state>
        @else
            {{-- A chave troca a cada item: os links "voltar ao rascunho" nascem
                 de novo com o rascunho certo em vez de reaproveitar o anterior. --}}
            <div wire:key="item-{{ $item->id }}" class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                {{-- ---------- A decisão ---------- --}}
                <div class="space-y-4 lg:col-span-2">
                    {{-- O que a pessoa escreveu --}}
                    <x-filament::section compact>
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 ring-1 ring-primary-100">
                                {{ Str::upper(Str::substr($item->ig_username ?: 'P', 0, 1)) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                    <span class="font-semibold text-gray-950">
                                        {{ $item->ig_username ? '@' . $item->ig_username : 'Paciente' }}
                                    </span>
                                    <span class="text-gray-300" aria-hidden="true">·</span>
                                    <span class="inline-flex items-center gap-1 text-gray-500">
                                        <x-filament::icon
                                            :icon="$item->canal === IaCanal::Comentario ? Heroicon::OutlinedChatBubbleOvalLeft : Heroicon::OutlinedPaperAirplane"
                                            class="h-4 w-4"
                                        />
                                        {{ $item->canal->getLabel() }}
                                    </span>
                                    <span class="text-gray-300" aria-hidden="true">·</span>
                                    <time
                                        class="text-gray-500"
                                        datetime="{{ ($item->comentario_em ?? $item->created_at)?->toIso8601String() }}"
                                        title="{{ ($item->comentario_em ?? $item->created_at)?->format('d/m/Y H:i') }}"
                                    >
                                        {{ ($item->comentario_em ?? $item->created_at)?->diffForHumans() }}
                                    </time>
                                </div>

                                <div class="mt-2 whitespace-pre-line rounded-2xl rounded-tl-md bg-gray-100 px-4 py-3 text-base leading-relaxed text-gray-950">{{ $item->textoDaPessoa() ?: '(sem texto)' }}</div>
                            </div>
                        </div>
                    </x-filament::section>

                    @if ($item->intent === IaIntent::NaoSei)
                        <div class="flex gap-3 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm">
                            <x-filament::icon :icon="Heroicon::OutlinedLightBulb" class="h-5 w-5 shrink-0 text-danger-600" />
                            <div>
                                <p class="font-semibold text-danger-800">A agente não encontrou resposta na base.</p>
                                <p class="mt-1 text-danger-700">
                                    O que você escrever aqui vira card validado na base de conhecimento — na próxima
                                    vez que perguntarem isso, ela responde sozinha.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- O que vai sair --}}
                    <x-filament::section heading="Resposta que vai sair" :icon="Heroicon::OutlinedChatBubbleLeftRight" compact>
                        <x-slot name="description">
                            Edite à vontade — o quanto você mexe vira a nota da agente.
                            Campo em branco significa que aquele canal não recebe nada.
                        </x-slot>

                        <div class="space-y-5">
                            @if ($item->canal === IaCanal::Comentario)
                                <div x-data="{ rascunho: @js((string) $item->rascunho_comentario) }">
                                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                                        <label for="campo-comentario" class="text-sm font-medium text-gray-950">
                                            Resposta pública no comentário
                                            <span class="font-normal text-gray-500">— todo mundo vê no post</span>
                                        </label>
                                        <div
                                            x-cloak
                                            x-show="rascunho !== '' && ($wire.comentario ?? '') !== rascunho"
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <x-filament::badge color="info" size="sm">editado</x-filament::badge>
                                            <button
                                                type="button"
                                                x-on:click="$wire.comentario = rascunho"
                                                class="font-medium text-primary-600 hover:underline"
                                            >
                                                Voltar ao rascunho da agente
                                            </button>
                                        </div>
                                    </div>
                                    <x-filament::input.wrapper class="fi-fo-textarea">
                                        <textarea
                                            id="campo-comentario"
                                            wire:model="comentario"
                                            rows="2"
                                            placeholder="Deixe vazio para não responder publicamente"
                                        ></textarea>
                                    </x-filament::input.wrapper>
                                </div>
                            @endif

                            <div x-data="{ rascunho: @js((string) $item->rascunho_dm) }">
                                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                                    <label for="campo-dm" class="text-sm font-medium text-gray-950">
                                        Direct
                                        <span class="font-normal text-gray-500">— mensagem privada</span>
                                    </label>
                                    <div
                                        x-cloak
                                        x-show="rascunho !== '' && ($wire.dm ?? '') !== rascunho"
                                        class="flex items-center gap-2 text-xs"
                                    >
                                        <x-filament::badge color="info" size="sm">editado</x-filament::badge>
                                        <button
                                            type="button"
                                            x-on:click="$wire.dm = rascunho"
                                            class="font-medium text-primary-600 hover:underline"
                                        >
                                            Voltar ao rascunho da agente
                                        </button>
                                    </div>
                                </div>
                                <x-filament::input.wrapper class="fi-fo-textarea">
                                    <textarea
                                        id="campo-dm"
                                        x-ref="campoDm"
                                        wire:model="dm"
                                        rows="7"
                                        placeholder="Deixe vazio para não mandar direct"
                                    ></textarea>
                                </x-filament::input.wrapper>
                            </div>

                            @php $materiaisDisponiveis = $this->materiaisDisponiveis(); @endphp
                            @if ($materiaisDisponiveis !== [])
                                <div>
                                    <p class="mb-1.5 text-sm font-medium text-gray-950">
                                        Vai junto com o direct
                                        <span class="font-normal text-gray-500">— marcado = a agente escolheu; marque ou desmarque. O que você deixar vira treino: ela aprende qual documento vai com qual assunto.</span>
                                    </p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($materiaisDisponiveis as $m)
                                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                                                <x-filament::input.checkbox wire:model="materiais" value="{{ $m['codigo'] }}" class="mt-0.5" />
                                                <span class="min-w-0">
                                                    <span class="block font-medium text-gray-950">{{ $m['nome'] }}</span>
                                                    <span class="block text-xs text-gray-500">
                                                        {{ $m['tipo'] === 'imagem' ? 'imagem, vai como anexo' : 'vai como link' }}
                                                        @if (filled($m['quando_usar']))
                                                            — {{ $m['quando_usar'] }}
                                                        @endif
                                                    </span>
                                                    <span class="mt-1 flex flex-wrap gap-3 text-xs font-medium">
                                                        @if ($m['url'])
                                                            <a href="{{ $m['url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">ver material</a>
                                                        @endif
                                                        <a href="{{ $this->urlDoMaterial($m['codigo']) }}" target="_blank" rel="noopener" class="text-gray-500 hover:underline">ajustar regra de uso</a>
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label for="campo-obs" class="mb-1.5 block text-sm font-medium text-gray-950">
                                    Observação para o histórico
                                    <span class="font-normal text-gray-500">(opcional)</span>
                                </label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        id="campo-obs"
                                        type="text"
                                        wire:model="observacao"
                                        placeholder="Ex.: corrigi o programa, ela ofereceu o Raiz no lugar do Pleno"
                                    />
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                    </x-filament::section>

                    {{-- Barra de decisão: fica presa no rodapé enquanto o texto rola,
                         para o aceite e os botões nunca saírem da tela.
                         A regra do aceite também é exigida no serviço, então não
                         existe caminho que passe por fora daqui. --}}
                    <div class="sticky bottom-0 z-10 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-lg shadow-gray-950/5 backdrop-blur">
                        <label
                            class="-m-2 flex cursor-pointer items-start gap-3 rounded-lg p-2 text-sm transition"
                            x-bind:class="lembrandoAceite && 'bg-warning-50 ring-2 ring-warning-400'"
                        >
                            <x-filament::input.checkbox wire:model="aceite" x-ref="aceite" class="mt-0.5" />
                            <span class="text-gray-700">
                                <strong class="text-gray-950">Assumo a responsabilidade por esta resposta.</strong>
                                Ela será enviada em nome de {{ $item->doctor?->nome ?? 'a clínica' }} exatamente
                                como está escrita, e a revisão fica registrada com meu nome.
                            </span>
                        </label>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <x-filament::button
                                x-ref="btnAprovar"
                                x-on:click="aprovar()"
                                wire:target="aprovar"
                                size="lg"
                                :icon="Heroicon::OutlinedPaperAirplane"
                            >
                                Aprovar e enviar
                                <kbd class="ms-1 hidden rounded border border-current px-1 font-mono text-xs leading-4 opacity-60 sm:inline-block">A</kbd>
                            </x-filament::button>

                            <x-filament::button
                                wire:click="pular"
                                color="gray"
                                size="lg"
                                :icon="Heroicon::OutlinedArrowRightCircle"
                            >
                                Pular
                                <kbd class="ms-1 hidden rounded border border-current px-1 font-mono text-xs leading-4 opacity-60 sm:inline-block">S</kbd>
                            </x-filament::button>

                            <x-filament::button
                                x-on:click="$dispatch('open-modal', { id: 'rejeitar' })"
                                color="danger"
                                size="lg"
                                outlined
                                :icon="Heroicon::OutlinedNoSymbol"
                            >
                                Rejeitar
                                <kbd class="ms-1 hidden rounded border border-current px-1 font-mono text-xs leading-4 opacity-60 sm:inline-block">R</kbd>
                            </x-filament::button>

                            <p x-cloak x-show="! $wire.aceite" class="text-sm text-gray-500 sm:ms-auto">
                                Marque o aceite para enviar.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ---------- O contexto ---------- --}}
                <div class="space-y-4">
                    <x-filament::section heading="Por que está aqui" :icon="Heroicon::OutlinedInformationCircle" compact>
                        <div class="flex flex-wrap gap-2">
                            <x-filament::badge :color="$item->motivo_fila->getColor()">
                                {{ $item->motivo_fila->getLabel() }}
                            </x-filament::badge>
                            <x-filament::badge :color="$item->intent->getColor()">
                                {{ $item->intent->getLabel() }}
                            </x-filament::badge>
                            @if ($item->estaAtrasado())
                                <x-filament::badge color="danger" :icon="Heroicon::OutlinedClock">Passou do prazo</x-filament::badge>
                            @endif
                        </div>

                        <dl class="mt-4 divide-y divide-gray-100 text-sm">
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-gray-500">Na fila há</dt>
                                <dd class="font-medium text-gray-950">{{ $item->created_at?->diffForHumans(null, true) }}</dd>
                            </div>
                            @if ($item->expira_em)
                                <div class="flex justify-between gap-4 py-2">
                                    <dt class="text-gray-500">Prazo</dt>
                                    <dd @class([
                                        'font-medium',
                                        'text-danger-700' => $item->estaAtrasado(),
                                        'text-gray-950' => ! $item->estaAtrasado(),
                                    ])>
                                        {{ $item->estaAtrasado() ? 'venceu' : 'vence' }} {{ $item->expira_em->diffForHumans() }}
                                    </dd>
                                </div>
                            @endif
                            @if ($item->confianca !== null)
                                <div class="flex justify-between gap-4 py-2">
                                    <dt class="text-gray-500">Confiança da agente</dt>
                                    <dd class="font-medium tabular-nums text-gray-950">{{ number_format($item->confianca * 100, 0) }}%</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-gray-500">Modelo</dt>
                                <dd class="inline-flex items-center gap-1 font-mono text-xs font-medium text-gray-950">
                                    <x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $item->modelo ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-gray-500">Instruções</dt>
                                <dd class="font-medium text-gray-950">
                                    {{ $item->promptVersion ? 'v' . $item->promptVersion->versao : '—' }}
                                </dd>
                            </div>
                        </dl>
                    </x-filament::section>

                    @if (filled($item->post_media_url) || filled($item->post_caption) || filled($item->post_permalink))
                        <x-filament::section heading="O post" :icon="Heroicon::OutlinedPhoto" compact collapsible>
                            @if ($item->post_media_url)
                                <img
                                    src="{{ $item->post_media_url }}"
                                    alt="Imagem do post"
                                    class="mb-3 max-h-72 w-full rounded-lg object-cover"
                                    loading="lazy"
                                >
                            @endif

                            @if (filled($item->post_caption))
                                <p class="whitespace-pre-line text-sm text-gray-600">{{ Str::limit($item->post_caption, 400) }}</p>
                            @endif

                            @if ($item->post_permalink)
                                <x-filament::link
                                    :href="$item->post_permalink"
                                    target="_blank"
                                    rel="noopener"
                                    size="sm"
                                    :icon="Heroicon::OutlinedArrowTopRightOnSquare"
                                    icon-position="after"
                                    class="mt-3"
                                >
                                    Abrir no Instagram
                                </x-filament::link>
                            @endif
                        </x-filament::section>
                    @endif

                    @if (filled($item->historico))
                        <x-filament::section
                            heading="Conversa anterior"
                            :icon="Heroicon::OutlinedChatBubbleLeftEllipsis"
                            compact
                            collapsible
                            collapsed
                        >
                            <div class="space-y-2 text-sm">
                                @foreach ($item->historico as $msg)
                                    @php
                                        $papel = Str::lower((string) data_get($msg, 'role', data_get($msg, 'de', '')));
                                        $daAgente = in_array($papel, ['assistant', 'agente', 'ia', 'bot'], true);
                                        $autor = match (true) {
                                            $daAgente => 'Agente',
                                            in_array($papel, ['user', 'paciente', 'cliente', 'lead'], true) => 'Paciente',
                                            default => Str::ucfirst($papel ?: '—'),
                                        };
                                    @endphp
                                    <div @class(['flex', 'justify-end' => $daAgente])>
                                        <div @class([
                                            'max-w-[85%] rounded-2xl px-3 py-2',
                                            'rounded-br-md bg-primary-50 text-primary-950' => $daAgente,
                                            'rounded-bl-md bg-gray-100 text-gray-800' => ! $daAgente,
                                        ])>
                                            <p class="text-xs font-medium opacity-60">{{ $autor }}</p>
                                            <p class="whitespace-pre-line">{{ data_get($msg, 'content', data_get($msg, 'texto', '')) }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif

                    @if ($fila->count() > 1)
                        <x-filament::section heading="Na fila" :icon="Heroicon::OutlinedQueueList" compact>
                            <x-slot name="afterHeader">
                                <x-filament::badge color="gray" size="sm">{{ $fila->count() }}</x-filament::badge>
                            </x-slot>

                            <ul class="-mx-2 max-h-96 space-y-0.5 overflow-y-auto">
                                @foreach ($fila as $outro)
                                    @php
                                        $atual = $outro->id === $item->id;
                                        $pulado = in_array($outro->id, $this->pulados, true);
                                    @endphp
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="abrir({{ $outro->id }})"
                                            @disabled($atual)
                                            @if ($atual) aria-current="true" @endif
                                            @class([
                                                'flex w-full items-start gap-2.5 rounded-lg px-2 py-2 text-left text-sm transition',
                                                'bg-primary-50 ring-1 ring-primary-200' => $atual,
                                                'hover:bg-gray-50' => ! $atual,
                                                'opacity-60' => $pulado && ! $atual,
                                            ])
                                        >
                                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $pontos[$outro->intent->getColor()] ?? 'bg-gray-400' }}"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="line-clamp-2 text-gray-800">{{ Str::limit($outro->textoDaPessoa(), 120) ?: '(sem texto)' }}</span>
                                                <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-gray-500">
                                                    {{ $outro->intent->getLabel() }}
                                                    · {{ $outro->created_at?->diffForHumans(null, true) }}
                                                    @if ($outro->estaAtrasado())
                                                        <span class="font-medium text-danger-600">· atrasado</span>
                                                    @endif
                                                    @if ($pulado)
                                                        <span>· pulado</span>
                                                    @endif
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </x-filament::section>
                    @endif
                </div>
            </div>

            <x-filament::modal id="rejeitar" width="lg" :icon="Heroicon::OutlinedNoSymbol" icon-color="danger">
                <x-slot name="heading">Rejeitar e ficar em silêncio</x-slot>

                <x-slot name="description">
                    Nada será enviado. O item conta como erro na acurácia e o motivo entra no
                    histórico de treinamento.
                </x-slot>

                <div class="space-y-3">
                    <label for="campo-motivo" class="block text-sm font-medium text-gray-950">
                        Por que esta resposta não deveria existir?
                    </label>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($sugestoesDeRejeicao as $sugestao)
                            <button
                                type="button"
                                x-on:click="$wire.motivoRejeicao = @js($sugestao)"
                                class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs text-gray-700 transition hover:border-gray-300 hover:bg-gray-50"
                            >
                                {{ $sugestao }}
                            </button>
                        @endforeach
                    </div>

                    <x-filament::input.wrapper class="fi-fo-textarea">
                        <textarea
                            id="campo-motivo"
                            wire:model="motivoRejeicao"
                            rows="3"
                            placeholder="Escreva com suas palavras — é o que ensina a agente."
                        ></textarea>
                    </x-filament::input.wrapper>
                </div>

                <x-slot name="footer">
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button wire:click="rejeitar" wire:target="rejeitar" color="danger">
                            Confirmar rejeição
                        </x-filament::button>
                        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'rejeitar' })">
                            Cancelar
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::modal>
        @endif
    </div>
</x-filament-panels::page>
