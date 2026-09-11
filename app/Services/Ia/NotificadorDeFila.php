<?php

namespace App\Services\Ia;

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Models\IaApproval;
use App\Models\IaGateSetting;
use App\Models\IaPushDevice;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa a equipe que existe pendência. Três canais, todos opcionais:
 *
 *   painel  — notificação no sininho do Filament (sempre)
 *   push    — FCM para o celular de quem registrou aparelho
 *   e-mail  — resumo, configurado por agente
 *
 * Notificação nunca derruba o fluxo: cada canal falha em silêncio no log.
 */
class NotificadorDeFila
{
    /** Pendência que não pode esperar o resumo: a agente não soube responder. */
    public function urgente(IaApproval $item): void
    {
        $titulo = $item->intent->value === 'nao_sei'
            ? 'A agente não soube responder'
            : 'Pendência na fila de aprovação';

        $corpo = mb_substr(trim($item->textoDaPessoa()), 0, 140);
        $destinatarios = $this->destinatarios($item->doctor_id);

        foreach ($destinatarios as $user) {
            Notification::make()
                ->warning()
                ->title($titulo)
                ->body($corpo !== '' ? "\"{$corpo}\"" : 'Abra a fila para revisar.')
                ->sendToDatabase($user);
        }

        $this->push(
            $item->doctor_id,
            $destinatarios,
            $titulo,
            $corpo !== '' ? $corpo : 'Abra a fila para revisar.',
        );

        $item->update(['notificado_em' => now()]);
    }

    /**
     * Resumo periódico do vigia da fila.
     *
     * @param  array{pendentes: int, nao_sei: int, atrasados: int}  $resumo
     */
    public function resumo(int $doctorId, array $resumo): void
    {
        if ($resumo['pendentes'] === 0) {
            return;
        }

        $cfg = IaGateSetting::paraAgente($doctorId);

        $titulo = "{$resumo['pendentes']} resposta(s) aguardando aprovação";
        $partes = [];

        if ($resumo['nao_sei'] > 0) {
            $partes[] = "{$resumo['nao_sei']} que a agente não soube responder";
        }

        if ($resumo['atrasados'] > 0) {
            $partes[] = "{$resumo['atrasados']} passando do prazo";
        }

        $corpo = $partes === [] ? 'Abra a fila para revisar.' : implode(' · ', $partes);
        $destinatarios = $this->destinatarios($doctorId);

        foreach ($destinatarios as $user) {
            Notification::make()
                ->warning()
                ->title($titulo)
                ->body($corpo)
                ->sendToDatabase($user);
        }

        $this->push($doctorId, $destinatarios, $titulo, $corpo);
        $this->email($cfg, $titulo, $corpo);
    }

    /**
     * Quem enxerga esta agente: admin e gestor veem tudo; recepção só a
     * agente do médico do seu escopo — o mesmo critério das outras telas.
     *
     * @return array<int, User>
     */
    private function destinatarios(int $doctorId): array
    {
        $agente = \App\Models\Doctor::query()->whereKey($doctorId)->value('agente');

        $escopo = match ($agente) {
            'duda' => DoctorScope::Eduardo,
            'luna' => DoctorScope::Vanessa,
            default => null,
        };

        return User::query()
            ->where(function ($q) use ($escopo): void {
                $q->whereIn('role', [UserRole::Admin->value, UserRole::Gestor->value]);

                if ($escopo !== null) {
                    $q->orWhere(fn ($s) => $s
                        ->where('role', UserRole::Recepcao->value)
                        ->whereIn('doctor_scope', [$escopo->value, DoctorScope::Ambos->value]));
                }
            })
            ->get()
            ->all();
    }

    /**
     * Push via FCM HTTP v1. Sem credencial configurada, não faz nada —
     * o painel e o e-mail já cobrem o aviso.
     *
     * @param  array<int, User>  $users
     */
    private function push(int $doctorId, array $users, string $titulo, string $corpo): void
    {
        if (! IaGateSetting::paraAgente($doctorId)->notif_push) {
            return;
        }

        $token = config('painel.ia.fcm_server_key');
        $url = config('painel.ia.fcm_url');

        if (blank($token) || blank($url) || $users === []) {
            return;
        }

        $devices = IaPushDevice::query()
            ->whereIn('user_id', array_map(fn (User $u): int => $u->id, $users))
            ->where('ativo', true)
            ->pluck('token');

        foreach ($devices as $device) {
            try {
                Http::withToken((string) $token)
                    ->timeout(8)
                    ->post((string) $url, [
                        'message' => [
                            'token' => $device,
                            'notification' => ['title' => $titulo, 'body' => $corpo],
                            'webpush' => [
                                'fcm_options' => ['link' => url('/ia/fila-de-aprovacao')],
                            ],
                        ],
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Falha no push da fila de IA', ['erro' => $e->getMessage()]);
            }
        }
    }

    private function email(IaGateSetting $cfg, string $titulo, string $corpo): void
    {
        $emails = array_filter($cfg->notif_emails ?? []);

        if ($emails === []) {
            return;
        }

        try {
            Mail::raw(
                $corpo . "\n\n" . url('/ia/fila-de-aprovacao'),
                fn ($m) => $m->to($emails)->subject('[Painel Ottoboni] ' . $titulo)
            );
        } catch (\Throwable $e) {
            Log::warning('Falha no e-mail da fila de IA', ['erro' => $e->getMessage()]);
        }
    }
}
