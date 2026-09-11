<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Versão imprimível de prescrição, pedido de exame ou atestado.
 */
class ImprimirRegistro extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PatientResource::class;

    protected string $view = 'filament.pacientes.imprimir-registro';

    public ?MedicalRecord $registro = null;

    // Parâmetro com nome diferente da propriedade: o Livewire faria o
    // model binding de {registro} sem passar pelo escopo do paciente.
    public function mount(int|string $record, int|string $registroId): void
    {
        // resolveRecord usa a consulta com escopo do resource.
        $this->record = $this->resolveRecord($record);

        abort_unless(PatientResource::podeVerProntuario(), 403);

        /** @var Patient $patient */
        $patient = $this->record;

        $this->registro = MedicalRecord::query()
            ->where('patient_id', $patient->id)
            ->with(['doctor', 'autor'])
            ->findOrFail($registroId);

        abort_unless($this->registro->tipo->imprimivel(), 404);

        AuditLog::registrar('imprimiu_registro_prontuario', [
            'patient_id' => $patient->id,
            'medical_record_id' => $this->registro->id,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->registro !== null ? $this->registro->titulo : 'Imprimir';
    }
}
