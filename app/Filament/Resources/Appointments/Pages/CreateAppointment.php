<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\AvisoDaAgenda;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\ConflitoDeHorario;
use App\Services\Agenda\ResultadoDaAgenda;
use App\Support\Demonstracao;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    private ?ResultadoDaAgenda $resultado = null;

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    public function mount(): void
    {
        parent::mount();

        // Vindo da Agenda com horário escolhido (?data=2026-09-14&hora=08:30&medico=1).
        $this->form->fill(array_filter([
            'data' => request()->query('data'),
            'hora' => request()->query('hora'),
            'doctor_id' => request()->integer('medico') ?: null,
            'tipo' => 'consulta',
            'duracao_min' => 30,
        ]));
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $this->resultado = app(AgendaService::class)->agendar(AppointmentForm::paraServico($data));
        } catch (ConflitoDeHorario|DomainException $e) {
            Notification::make()->danger()->title('Não foi possível agendar')->body(e($e->getMessage()))->send();
            $this->halt();
        }

        return $this->resultado->agendamento;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function afterCreate(): void
    {
        if ($this->resultado !== null) {
            AvisoDaAgenda::enviar($this->resultado, 'Agendamento criado');
        }
    }
}
