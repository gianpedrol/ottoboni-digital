<?php

namespace App\Console\Commands;

use App\Enums\IaApprovalStatus;
use App\Enums\IaIntent;
use App\Models\Doctor;
use App\Models\IaApproval;
use App\Models\IaGateSetting;
use App\Services\Ia\NotificadorDeFila;
use Illuminate\Console\Command;

/**
 * Vigia da fila de aprovação.
 *
 * Três tarefas, a cada 5 minutos:
 *   1. avisa a equipe do que está pendente (resumo, não spam)
 *   2. marca como expirado o que passou de 24h sem ninguém olhar
 *   3. NUNCA envia nada por conta própria — o prazo estourado só destaca o
 *      item e volta a notificar; quem libera resposta é sempre uma pessoa
 */
class IaVigiaFila extends Command
{
    protected $signature = 'ia:vigia-fila';

    protected $description = 'Notifica pendências da fila de aprovação e expira itens abandonados';

    public function handle(NotificadorDeFila $notificador): int
    {
        $agentes = Doctor::query()->where('ativo', true)->get();

        foreach ($agentes as $agente) {
            $expirados = $this->expirarAbandonados($agente->id);

            $itens = IaApproval::query()
                ->where('doctor_id', $agente->id)
                ->pendentes()
                ->get(['intent', 'expira_em', 'notificado_em']);

            if ($itens->isEmpty()) {
                $this->line("{$agente->agente}: fila vazia" . ($expirados > 0 ? " ({$expirados} expirados)" : ''));

                continue;
            }

            $resumo = [
                'pendentes' => $itens->count(),
                'nao_sei' => $itens->where('intent', IaIntent::NaoSei)->count(),
                'atrasados' => $itens->filter(
                    fn (IaApproval $i): bool => $i->expira_em !== null && $i->expira_em->isPast()
                )->count(),
            ];

            // Só reavisa quando existe algo novo ou algo atrasado — resumo a
            // cada 5 minutos sobre a mesma fila parada vira ruído e a equipe
            // passa a ignorar o aviso justamente quando ele importa.
            $novos = $itens->whereNull('notificado_em')->count();

            if ($novos === 0 && $resumo['atrasados'] === 0) {
                $this->line("{$agente->agente}: {$resumo['pendentes']} pendente(s), nada novo");

                continue;
            }

            $notificador->resumo($agente->id, $resumo);

            IaApproval::query()
                ->where('doctor_id', $agente->id)
                ->pendentes()
                ->whereNull('notificado_em')
                ->update(['notificado_em' => now()]);

            $this->info("{$agente->agente}: avisado — {$resumo['pendentes']} pendente(s), "
                . "{$resumo['nao_sei']} sem resposta na base, {$resumo['atrasados']} atrasado(s)");
        }

        return self::SUCCESS;
    }

    /**
     * Item parado há mais de 24h sai da fila como expirado: fica no histórico
     * e conta como lacuna, mas não polui a tela de quem está revisando hoje.
     */
    private function expirarAbandonados(int $doctorId): int
    {
        $limite = IaGateSetting::paraAgente($doctorId);

        return IaApproval::query()
            ->where('doctor_id', $doctorId)
            ->pendentes()
            ->where('created_at', '<', now()->subDay())
            ->update([
                'status' => IaApprovalStatus::Expirado,
                'observacao_humano' => 'Expirado automaticamente após 24h sem revisão'
                    . " (prazo configurado: {$limite->timeout_min} min).",
            ]);
    }
}
