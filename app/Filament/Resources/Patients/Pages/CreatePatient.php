<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Enums\KommoSyncStatus;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\Pacientes\SincronizadorKommo;
use App\Support\Demonstracao;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected bool $abrirAtendimento = false;

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->abrirAtendimento = (bool) Arr::pull($data, 'abrir_atendimento', false);
        $data['kommo_sync_status'] = KommoSyncStatus::Pendente;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Patient $patient */
        $patient = $this->getRecord();

        AuditLog::registrar('criou_paciente', ['patient_id' => $patient->id]);

        $resultado = app(SincronizadorKommo::class)->sincronizar($patient, $this->abrirAtendimento);

        Notification::make()
            ->title($resultado->titulo)
            ->body($resultado->mensagem)
            ->color($resultado->status->getColor())
            ->icon('heroicon-o-cloud-arrow-up')
            ->persistent()
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return PatientResource::getUrl('view', ['record' => $this->getRecord(), 'aba' => 'kommo::tab']);
    }
}
