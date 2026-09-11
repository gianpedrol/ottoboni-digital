<?php

namespace App\Services\Ia;

use App\Enums\IaApprovalStatus;
use App\Enums\IaIntent;
use App\Models\IaApproval;
use App\Models\IaGateSetting;

/**
 * Acurácia em janela móvel: só as últimas N revisões contam, para que a nota
 * reflita como a agente está HOJE e não a média histórica desde o primeiro dia.
 */
class CalculadoraDeAcuracia
{
    /**
     * @return array{intent: string, amostras: int, acuracia: ?float, taxa_sem_edicao: ?float, rejeitadas: int}
     */
    public function porIntent(int $doctorId, IaIntent $intent, ?int $janela = null): array
    {
        $janela ??= IaGateSetting::paraAgente($doctorId)->janela;

        $itens = IaApproval::query()
            ->where('doctor_id', $doctorId)
            ->avaliaveis()
            ->where('intent', $intent)
            ->orderByDesc('revisado_em')
            ->limit($janela)
            ->get(['score', 'grau_edicao', 'status']);

        if ($itens->isEmpty()) {
            return [
                'intent' => $intent->value,
                'amostras' => 0,
                'acuracia' => null,
                'taxa_sem_edicao' => null,
                'rejeitadas' => 0,
            ];
        }

        $semEdicao = $itens->filter(fn (IaApproval $i): bool => $i->grau_edicao?->value === 'sem_edicao')->count();

        return [
            'intent' => $intent->value,
            'amostras' => $itens->count(),
            'acuracia' => round((float) $itens->avg('score'), 3),
            'taxa_sem_edicao' => round($semEdicao / $itens->count(), 3),
            'rejeitadas' => $itens->where('status', IaApprovalStatus::Rejeitado)->count(),
        ];
    }

    /**
     * Nota geral da agente — é o número que vai no topo do painel e o que
     * dispara o kill switch.
     *
     * @return array{amostras: int, acuracia: ?float, taxa_sem_edicao: ?float, rejeitadas: int}
     */
    public function geral(int $doctorId, ?int $janela = null): array
    {
        $janela ??= IaGateSetting::paraAgente($doctorId)->janela;

        $itens = IaApproval::query()
            ->where('doctor_id', $doctorId)
            ->avaliaveis()
            ->orderByDesc('revisado_em')
            ->limit($janela)
            ->get(['score', 'grau_edicao', 'status']);

        if ($itens->isEmpty()) {
            return ['amostras' => 0, 'acuracia' => null, 'taxa_sem_edicao' => null, 'rejeitadas' => 0];
        }

        $semEdicao = $itens->filter(fn (IaApproval $i): bool => $i->grau_edicao?->value === 'sem_edicao')->count();

        return [
            'amostras' => $itens->count(),
            'acuracia' => round((float) $itens->avg('score'), 3),
            'taxa_sem_edicao' => round($semEdicao / $itens->count(), 3),
            'rejeitadas' => $itens->where('status', IaApprovalStatus::Rejeitado)->count(),
        ];
    }

    /**
     * Painel de acurácia: uma linha por assunto, já com o veredito do portão.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tabela(int $doctorId, PortaoDeAprovacao $portao): array
    {
        $linhas = [];

        foreach (IaIntent::cases() as $intent) {
            if ($intent === IaIntent::NaoSei) {
                continue; // não entra na nota: por definição ela não sabia
            }

            $dados = $this->porIntent($doctorId, $intent);
            $decisao = $portao->decidir($doctorId, $intent);

            $linhas[] = [
                ...$dados,
                'rotulo' => $intent->getLabel(),
                'liberado' => $decisao->enviarDireto,
                'situacao' => $decisao->enviarDireto ? 'Liberado' : $decisao->motivo->getLabel(),
            ];
        }

        return $linhas;
    }

    /**
     * As perguntas que mais caem em "não sei" — o mapa dos buracos da base.
     *
     * @return array<int, array{pergunta: string, vezes: int}>
     */
    public function lacunas(int $doctorId, int $limite = 20): array
    {
        return IaApproval::query()
            ->where('doctor_id', $doctorId)
            ->where('intent', IaIntent::NaoSei)
            ->get(['comentario_texto', 'mensagem_texto'])
            ->map(fn (IaApproval $a): string => mb_strtolower(trim($a->textoDaPessoa())))
            ->filter(fn (string $t): bool => $t !== '')
            ->countBy()
            ->sortDesc()
            ->take($limite)
            ->map(fn (int $vezes, string $pergunta): array => ['pergunta' => $pergunta, 'vezes' => $vezes])
            ->values()
            ->all();
    }
}
