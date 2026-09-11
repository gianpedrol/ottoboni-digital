<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\Pacientes\SincronizadorKommo;
use App\Support\Cpf;
use App\Support\Demonstracao;
use App\Support\Telefone;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    protected bool $abrirAtendimento = false;

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // CPF está em $hidden no model: só entra no formulário explicitamente.
        $data['cpf'] = Cpf::formatar($this->getRecord()->getAttribute('cpf'));
        $data['telefone'] = Telefone::paraMascara($data['telefone'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->abrirAtendimento = (bool) Arr::pull($data, 'abrir_atendimento', false);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Patient $patient */
        $patient = $this->getRecord();

        AuditLog::registrar('editou_paciente', ['patient_id' => $patient->id]);

        $resultado = app(SincronizadorKommo::class)->sincronizar($patient, $this->abrirAtendimento);

        Notification::make()
            ->title($resultado->titulo)
            ->body($resultado->mensagem)
            ->color($resultado->status->getColor())
            ->icon('heroicon-o-cloud-arrow-up')
            ->persistent()
            ->send();
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return PatientResource::getUrl('view', ['record' => $this->getRecord(), 'aba' => 'kommo::tab']);
    }
}
