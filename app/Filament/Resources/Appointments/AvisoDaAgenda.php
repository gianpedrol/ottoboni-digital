<?php

namespace App\Filament\Resources\Appointments;

use App\Models\Appointment;
use App\Services\Agenda\EfeitoKommo;
use App\Services\Agenda\ResultadoDaAgenda;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Notificações da agenda, incluindo o aviso "Simulado — no painel real o
 * Kommo seria atualizado".
 */
class AvisoDaAgenda
{
    public static function enviar(ResultadoDaAgenda $resultado, string $titulo): void
    {
        $agendamento = $resultado->agendamento->loadMissing(['patient', 'doctor']);

        Notification::make()
            ->success()
            ->title($titulo)
            ->body(e(self::resumo($agendamento)))
            ->send();

        if ($resultado->efeito !== null) {
            self::efeito($resultado->efeito, $agendamento);
        }
    }

    public static function efeito(EfeitoKommo $efeito, Appointment $agendamento): void
    {
        $linhas = array_map(fn (string $linha): string => e($linha), explode("\n", $efeito->texto()));

        $notificacao = Notification::make()
            ->title($efeito->titulo())
            ->body(implode('<br>', $linhas))
            ->icon(Heroicon::OutlinedBeaker)
            ->iconColor($efeito->temEnvio() ? 'warning' : 'gray');

        if ($efeito->temEnvio()) {
            $notificacao->persistent();
        }

        $url = $efeito->abrirProntuario ? self::urlPaciente($agendamento->patient_id) : null;

        if ($url !== null) {
            $notificacao->actions([
                Action::make('abrirProntuario')
                    ->label('Abrir prontuário')
                    ->button()
                    ->url($url),
            ]);
        }

        $notificacao->send();
    }

    public static function resumo(Appointment $agendamento): string
    {
        return sprintf(
            '%s · %s %s · %s',
            $agendamento->patient->nome ?? 'Paciente',
            $agendamento->inicioLocal()->format('d/m/Y'),
            $agendamento->faixaHorario(),
            $agendamento->doctor->nome ?? '',
        );
    }

    /**
     * Link para a ficha/prontuário do paciente. O resource de pacientes é de
     * outro módulo; se ainda não existir, não mostra o link.
     */
    public static function urlPaciente(?int $patientId): ?string
    {
        $classe = 'App\\Filament\\Resources\\Patients\\PatientResource';

        if ($patientId === null || ! class_exists($classe)) {
            return null;
        }

        try {
            return $classe::getUrl('view', ['record' => $patientId]);
        } catch (\Throwable) {
            return null;
        }
    }
}
