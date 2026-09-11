{{-- Corpo de um registro do prontuário. Usado no modal e na impressão. --}}
@php
    $tipos = \App\Enums\TipoRegistroProntuario::class;
    $dados = $registro->dados ?? [];
    $tipo = $registro->tipo;
    $impressao = $impressao ?? false;
    $simNao = [
        'tabagismo' => ['nao' => 'Não', 'ex' => 'Ex-tabagista', 'sim' => 'Sim'],
        'etilismo' => ['nao' => 'Não', 'social' => 'Social', 'frequente' => 'Frequente'],
    ];
    $rotuloClasse = 'font-medium text-gray-500' . ($impressao ? '' : ' dark:text-gray-400');
    $valorClasse = 'mt-0.5 whitespace-pre-line text-gray-950' . ($impressao ? '' : ' dark:text-white');
@endphp

<div class="space-y-4 text-sm">
    @if ($tipo === $tipos::Anamnese)
        @php
            $campos = [
                'queixa_principal' => 'Queixa principal',
                'historia_doenca_atual' => 'História da doença atual',
                'antecedentes' => 'Antecedentes pessoais e familiares',
                'cirurgias_previas' => 'Cirurgias prévias',
                'alergias' => 'Alergias',
                'medicacoes_em_uso' => 'Medicações em uso',
            ];
        @endphp
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ($campos as $chave => $rotulo)
                <div @class(['sm:col-span-2' => in_array($chave, ['queixa_principal', 'historia_doenca_atual'], true)])>
                    <dt class="{{ $rotuloClasse }}">{{ $rotulo }}</dt>
                    <dd class="{{ $valorClasse }}">{{ filled($dados[$chave] ?? null) ? $dados[$chave] : '—' }}</dd>
                </div>
            @endforeach
            <div>
                <dt class="{{ $rotuloClasse }}">Tabagismo</dt>
                <dd class="{{ $valorClasse }}">{{ $simNao['tabagismo'][$dados['tabagismo'] ?? ''] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="{{ $rotuloClasse }}">Etilismo</dt>
                <dd class="{{ $valorClasse }}">{{ $simNao['etilismo'][$dados['etilismo'] ?? ''] ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="{{ $rotuloClasse }}">Peso · altura · IMC</dt>
                <dd class="{{ $valorClasse }}">
                    {{ filled($dados['peso'] ?? null) ? $dados['peso'] . ' kg' : '—' }}
                    · {{ filled($dados['altura'] ?? null) ? $dados['altura'] . ' m' : '—' }}
                    · IMC {{ filled($dados['imc'] ?? null) ? number_format((float) $dados['imc'], 1, ',', '.') : '—' }}
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="{{ $rotuloClasse }}">Expectativas do paciente</dt>
                <dd class="{{ $valorClasse }}">{{ filled($dados['expectativas'] ?? null) ? $dados['expectativas'] : '—' }}</dd>
            </div>
        </dl>
    @elseif ($tipo === $tipos::Evolucao)
        <div class="fi-prose max-w-none">
            {!! str($registro->conteudo ?? '')->sanitizeHtml() !!}
        </div>
    @elseif ($tipo === $tipos::Prescricao)
        <ol class="list-decimal space-y-3 ps-5">
            @foreach ($dados['itens'] ?? [] as $item)
                <li>
                    <p class="font-semibold">{{ $item['medicamento'] ?? '' }} @if (filled($item['dose'] ?? null)) — {{ $item['dose'] }} @endif</p>
                    <p class="text-gray-700 {{ $impressao ? '' : 'dark:text-gray-300' }}">
                        {{ collect([$item['posologia'] ?? null, $item['duracao'] ?? null])->filter()->implode(' · ') }}
                    </p>
                </li>
            @endforeach
        </ol>
        @if (filled($registro->conteudo))
            <div>
                <p class="{{ $rotuloClasse }}">Orientações</p>
                <p class="{{ $valorClasse }}">{{ $registro->conteudo }}</p>
            </div>
        @endif
    @elseif ($tipo === $tipos::Exame)
        <p class="{{ $rotuloClasse }}">Solicito:</p>
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($dados['exames'] ?? [] as $exame)
                <li>{{ is_array($exame) ? ($exame['exame'] ?? '') : $exame }}</li>
            @endforeach
        </ul>
        @if (filled($dados['indicacao_clinica'] ?? null))
            <div>
                <p class="{{ $rotuloClasse }}">Indicação clínica</p>
                <p class="{{ $valorClasse }}">{{ $dados['indicacao_clinica'] }}</p>
            </div>
        @endif
    @elseif ($tipo === $tipos::Atestado)
        <p class="{{ $valorClasse }} leading-relaxed">{{ $registro->conteudo }}</p>
        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="{{ $rotuloClasse }}">Afastamento</dt>
                <dd class="{{ $valorClasse }}">{{ $dados['dias'] ?? '—' }} dia(s)</dd>
            </div>
            <div>
                <dt class="{{ $rotuloClasse }}">A partir de</dt>
                <dd class="{{ $valorClasse }}">{{ filled($dados['inicio'] ?? null) ? \Illuminate\Support\Carbon::parse($dados['inicio'])->format('d/m/Y') : '—' }}</dd>
            </div>
            <div>
                <dt class="{{ $rotuloClasse }}">CID</dt>
                <dd class="{{ $valorClasse }}">{{ filled($dados['cid'] ?? null) ? $dados['cid'] : 'Não informado' }}</dd>
            </div>
        </dl>
    @elseif ($tipo === $tipos::Foto)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($registro->anexos ?? [] as $foto)
                <figure class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                    <div class="flex aspect-[3/4] flex-col items-center justify-center gap-2 bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500">
                        <x-filament::icon icon="heroicon-o-camera" class="h-8 w-8" />
                        <span class="text-xs font-medium uppercase tracking-wide">
                            {{ \App\Models\MedicalRecord::ANGULOS[$foto['angulo'] ?? ''] ?? ($foto['angulo'] ?? 'Ângulo') }}
                        </span>
                    </div>
                    <figcaption class="space-y-1 p-2 text-xs">
                        <x-filament::badge :color="($foto['momento'] ?? '') === 'depois' ? 'success' : 'gray'" class="w-fit">
                            {{ ($foto['momento'] ?? '') === 'depois' ? 'Depois' : 'Antes' }}
                        </x-filament::badge>
                        @if (filled($foto['legenda'] ?? null))
                            <p class="text-gray-700 dark:text-gray-300">{{ $foto['legenda'] }}</p>
                        @endif
                        @if (filled($foto['arquivo'] ?? null))
                            <p class="text-gray-400">{{ $foto['arquivo'] }}</p>
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Protótipo: as imagens aparecem como marcadores com o ângulo e a legenda.</p>
        @if (filled($registro->conteudo))
            <p class="{{ $valorClasse }}">{{ $registro->conteudo }}</p>
        @endif
    @endif
</div>
