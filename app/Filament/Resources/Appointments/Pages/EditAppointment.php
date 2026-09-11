<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\Actions\AcoesDoAgendamento;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\AvisoDaAgenda;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\ConflitoDeHorario;
use App\Services\Agenda\ResultadoDaAgenda;
use App\Support\Demonstracao;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Appointment $record
 */
class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    private ?ResultadoDaAgenda $resultado = null;

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    protected function getHeaderActions(): array
    {
        $acoes = [
            ActionGroup::make(AcoesDoAgendamento::todas())
                ->label('Status')
                ->button()
                ->color('gray'),
        ];

        $url = AvisoDaAgenda::urlPaciente($this->record->patient_id);

        if ($url !== null) {
            $acoes[] = Action::make('paciente')
                ->label('Abrir paciente / prontuário')
                ->icon(Heroicon::OutlinedUserCircle)
                ->color('gray')
                ->url($url);
        }

        return $acoes;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...AppointmentForm::doAgendamento($this->record)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Appointment $record */
        try {
            $this->resultado = app(AgendaService::class)->atualizar($record, AppointmentForm::paraServico($data));
        } catch (ConflitoDeHorario|DomainException $e) {
            Notification::make()->danger()->title('Não foi possível salvar')->body(e($e->getMessage()))->send();
            $this->halt();
        }

        return $this->resultado->agendamento;
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function afterSave(): void
    {
        if ($this->resultado !== null) {
            AvisoDaAgenda::enviar($this->resultado, 'Agendamento salvo');
        }
    }
}
