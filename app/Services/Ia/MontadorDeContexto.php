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

/**
 * Monta o contexto que o n8n busca antes de gerar a resposta: prompt ativo,
 * regras inegociáveis, exemplos aprovados, cards da base e gatilhos.
 *
 * Os guardrails entram SEMPRE no fim do system prompt. O humano edita os
 * blocos, mas não consegue apagar as regras que não se negociam.
 */
class MontadorDeContexto
{
    private const MAX_EXEMPLOS = 12;

    private const MAX_CARDS = 80;

    /**
     * @return array<string, mixed>
     */
    public function paraAgente(Doctor $doctor, ?IaIntent $intent = null): array
    {
        $cfg = IaGateSetting::paraAgente($doctor->id);

        /** @var IaPromptVersion|null $versao */
        $versao = IaPromptVersion::query()
            ->where('doctor_id', $doctor->id)
            ->where('ativo', true)
            ->first();

        $guardrails = IaGuardrail::paraAgente($doctor->id);

        return [
            'agente' => $doctor->agente,
            'medico' => $doctor->nome,
            'modelo' => $cfg->modelo,
            'prompt_versao' => $versao?->versao,
            'prompt_version_id' => $versao?->id,
            'blocos' => $versao->blocos ?? [],
            'guardrails' => $guardrails,
            'system_prompt' => $this->systemPrompt($versao, $guardrails, $doctor),
            'exemplos' => $this->exemplos($doctor->id, $intent),
            'cards' => $this->cards($doctor->id),
            'gatilhos' => $this->gatilhos($doctor->id),
        ];
    }

    /**
     * O system prompt pronto para ir ao modelo: blocos editáveis, exemplos
     * aprovados e, por último, as regras inegociáveis.
     *
     * @param  array<int, string>  $guardrails
     */
    public function systemPrompt(?IaPromptVersion $versao, array $guardrails, Doctor $doctor): string
    {
        $partes = [];

        foreach ($versao->blocos ?? [] as $chave => $texto) {
            if (blank($texto)) {
                continue;
            }

            $titulo = IaPromptVersion::BLOCOS[$chave] ?? $chave;
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
     * @return array<int, array{id: int, pergunta: string, resposta: string, tags: array<int, string>}>
     */
    public function cards(int $doctorId): array
    {
        return IaCard::query()
            ->where('doctor_id', $doctorId)
            ->where('ativo', true)
            ->where('status', 'validado')
            ->orderBy('id')
            ->limit(self::MAX_CARDS)
            ->get()
            ->map(fn (IaCard $c): array => [
                'id' => $c->id,
                'pergunta' => $c->pergunta,
                'resposta' => $c->resposta,
                'tags' => $c->tags ?? [],
            ])
            ->all();
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
