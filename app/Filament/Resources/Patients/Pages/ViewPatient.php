<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\Pacientes\SincronizadorKommo;
use App\Support\Demonstracao;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        AuditLog::registrar('abriu_paciente', ['patient_id' => $this->getRecord()->getKey()]);
    }

    public function getTitle(): string|Htmlable
    {
        return (string) $this->getRecord()->getAttribute('nome');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simularSincronizacao')
                ->label('Simular envio ao Kommo')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->modalHeading('Simular envio ao Kommo')
                ->modalDescription('Monta de novo as requisições com os dados atuais do paciente. No protótipo nada é enviado.')
                ->modalSubmitActionLabel('Simular')
                ->schema([
                    Checkbox::make('abrir_atendimento')
                        ->label('Abrir atendimento no Kommo')
                        ->visible(fn (): bool => $this->getRecord()->getAttribute('kommo_lead_id') === null),
                ])
                ->action(function (array $data): void {
                    /** @var Patient $patient */
                    $patient = $this->getRecord();

                    $resultado = app(SincronizadorKommo::class)->sincronizar($patient, (bool) ($data['abrir_atendimento'] ?? false));

                    Notification::make()
                        ->title($resultado->titulo)
                        ->body($resultado->mensagem)
                        ->color($resultado->status->getColor())
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
