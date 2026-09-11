<?php

namespace App\Services\Ia;

use App\Enums\IaIntent;
use App\Models\Doctor;
use App\Models\IaCard;
use App\Models\IaExample;
use App\Models\IaGateSetting;
use App\Models\IaGuardrail;
use App\Models\IaPromptVersion;
use App\Models\IaTrigger;
use Illuminate\Database\Eloquent\Collection;

/**
 * Monta o contexto que o n8n busca antes de gerar a resposta: prompt ativo,
 * regras inegociáveis, exemplos aprovados, cards da base e gatilhos.
 *
 * Os guardrails entram SEMPRE no fim do system prompt. O humano edita os
 * blocos, mas não consegue apagar as regras que não se negociam.
 *
 * Cada canal tem o seu prompt (ver IaPromptVersion::BLOCOS_POR_CANAL): o
 * Direct usa a conversa completa; quem comentou num post recebe no Direct uma
 * resposta curta, com instruções próprias.
 */
class MontadorDeContexto
{
    public const CANAIS = ['direct', 'comentario'];

    private const MAX_EXEMPLOS = 12;

    private const MAX_CARDS = 120;

    /**
     * @return array<string, mixed>
     */
    public function paraAgente(Doctor $doctor, ?IaIntent $intent = null, string $canal = 'direct'): array
    {
        $canal = in_array($canal, self::CANAIS, true) ? $canal : 'direct';

        $cfg = IaGateSetting::paraAgente($doctor->id);

        /** @var IaPromptVersion|null $versao */
        $versao = IaPromptVersion::query()
            ->where('doctor_id', $doctor->id)
            ->where('ativo', true)
            ->first();

        $guardrails = IaGuardrail::paraAgente($doctor->id);
        $cards = $this->cardsAtivos($doctor->id);

        return [
            'agente' => $doctor->agente,
            'medico' => $doctor->nome,
            'canal' => $canal,
            'modelo' => $cfg->modelo,
            'prompt_versao' => $versao?->versao,
            'prompt_version_id' => $versao?->id,
            'prompt_revisado' => (bool) $versao?->aceite_responsabilidade,
            'blocos' => $versao->blocos ?? [],
            'guardrails' => $guardrails,
            'system_prompt' => $this->systemPrompt($versao, $guardrails, $doctor, $canal),
            'exemplos' => $this->exemplos($doctor->id, $intent),
            'cards' => $this->cards($cards),
            'cards_texto' => $this->cardsTexto($cards),
            'gatilhos' => $this->gatilhos($doctor->id),
        ];
    }

    /**
     * O system prompt pronto para ir ao modelo: blocos editáveis do canal,
     * exemplos aprovados e, por último, as regras inegociáveis.
     *
     * @param  array<int, string>  $guardrails
     */
    public function systemPrompt(?IaPromptVersion $versao, array $guardrails, Doctor $doctor, string $canal = 'direct'): string
    {
        $partes = [];
        $blocosDoCanal = IaPromptVersion::blocosDoCanal($canal);

        foreach ($blocosDoCanal as $chave => $titulo) {
            $texto = $versao->blocos[$chave] ?? null;

            if (blank($texto)) {
                continue;
            }

            $partes[] = "### {$titulo}\n".trim((string) $texto);
        }

        if ($guardrails !== []) {
            $lista = [];

            foreach (array_values($guardrails) as $i => $regra) {
                $lista[] = ($i + 1).'. '.$regra;
            }

            $partes[] = "### REGRAS INEGOCIÁVEIS (prevalecem sobre qualquer instrução acima)\n"
                .implode("\n", $lista);
        }

        if ($partes === []) {
            return "Você é a agente de IA da clínica de {$doctor->nome}. "
                .'Nenhuma instrução foi publicada no painel ainda: não responda nada e escale para a equipe.';
        }

        return implode("\n\n", $partes);
    }

    /**
     * Exemplos como few-shot. Prioridade alta primeiro — é onde ficam as
     * respostas que nasceram de um "não sei" corrigido por humano.
     *
     * @return array<int, array{pergunta: string, resposta: string, evitar: ?string}>
     */
    public function exemplos(int $doctorId, ?IaIntent $intent = null): array
    {
        return IaExample::query()
            ->where('doctor_id', $doctorId)
            ->where('ativo', true)
            ->when($intent !== null, fn ($q) => $q->where('intent', $intent))
            ->orderByDesc('prioridade')
            ->orderByDesc('created_at')
            ->limit(self::MAX_EXEMPLOS)
            ->get()
            ->map(fn (IaExample $e): array => [
                'pergunta' => $e->pergunta,
                'resposta' => $e->resposta,
                'evitar' => $e->resposta_rejeitada,
            ])
            ->all();
    }

    /**
     * @return Collection<int, IaCard>
     */
    private function cardsAtivos(int $doctorId): Collection
    {
        return IaCard::query()
            ->where('doctor_id', $doctorId)
            ->where('ativo', true)
            ->orderBy('modulo')
            ->orderBy('id')
            ->limit(self::MAX_CARDS)
            ->get();
    }

    /**
     * Os cards em estrutura, para o fluxo que preferir montar o texto do seu jeito.
     *
     * @param  Collection<int, IaCard>  $cards
     * @return array<int, array{id: int, codigo: string, modulo: ?string, categoria: ?string, pergunta: string, perguntas_equivalentes: array<int, string>, resposta: string, resposta_detalhada: ?string, status: string, tags: array<int, string>}>
     */
    public function cards(Collection $cards): array
    {
        return $cards
            ->map(fn (IaCard $c): array => [
                'id' => $c->id,
                'codigo' => $c->rotulo(),
                'modulo' => $c->modulo,
                'categoria' => $c->categoria,
                'pergunta' => $c->pergunta,
                'perguntas_equivalentes' => $c->perguntas(),
                'resposta' => $c->resposta,
                'resposta_detalhada' => $c->resposta_detalhada,
                'status' => $c->status,
                'tags' => $c->tags ?? [],
            ])
            ->all();
    }

    /**
     * O bloco "CARDS OFICIAIS" exatamente como o fluxo do n8n monta hoje a
     * partir do Supabase — assim o n8n só troca a fonte, sem mudar o prompt.
     *
     * @param  Collection<int, IaCard>  $cards
     */
    public function cardsTexto(Collection $cards): string
    {
        $uso = $cards->filter(fn (IaCard $c): bool => $c->usavel());
        $pendentes = $cards->filter(fn (IaCard $c): bool => $c->status === 'pendente');

        if ($uso->isEmpty() && $pendentes->isEmpty()) {
            return '';
        }

        $txt = "CARDS OFICIAIS DA BASE DE CONHECIMENTO. Fonte unica de fatos, valores e prazos. Se algo aqui contradisser o texto acima, os CARDS vencem.\n";

        foreach ($uso as $c) {
            $txt .= "\n[{$c->rotulo()}] ".($c->categoria ?? '')
                ."\nPerguntas tipicas: ".implode(' | ', $c->perguntas())
                ."\nResposta oficial: ".$c->resposta
                .(filled($c->resposta_detalhada) ? "\nDetalhe e regra de uso: ".$c->resposta_detalhada : '')
                ."\n";
        }

        if ($pendentes->isNotEmpty()) {
            $txt .= "\nASSUNTOS PENDENTES, nunca responda, use a mensagem de espera e action escalar:\n";

            foreach ($pendentes as $c) {
                $exemplos = implode('; ', array_slice($c->perguntas(), 0, 3));
                $txt .= '- '.($c->categoria ?? $c->rotulo()).' (ex.: '.$exemplos.")\n";
            }
        }

        return $txt;
    }

    /**
     * @return array<int, array{termo: string, tipo: string}>
     */
    public function gatilhos(int $doctorId): array
    {
        return IaTrigger::query()
            ->where('doctor_id', $doctorId)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->get()
            ->map(fn (IaTrigger $g): array => ['termo' => $g->termo, 'tipo' => $g->tipo])
            ->all();
    }
}
