<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Pages\Agenda;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Support\Demonstracao;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agenda')
                ->label('Ver agenda')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(Agenda::getUrl()),
            CreateAction::make()
                ->label('Novo agendamento'),
        ];
    }
}
