<?php

namespace App\Filament\Resources\Appointments\Actions;

use App\Enums\StatusAgendamento;
use App\Filament\Resources\Appointments\AvisoDaAgenda;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\ConflitoDeHorario;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Ações rápidas da recepção. As mesmas instâncias servem para a tabela do
 * resource, a visão "dia" e os modais da visão "semana".
 */
class AcoesDoAgendamento
{
    /**
     * @return array<int, Action>
     */
    public static function todas(): array
    {
        return [
            self::confirmar(),
            self::realizado(),
            self::faltou(),
            self::remarcar(),
            self::cancelar(),
        ];
    }

    public static function confirmar(): Action
    {
        return Action::make('confirmar')
            ->label('Confirmar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (?Appointment $record): bool => $record?->status === StatusAgendamento::Agendado)
            ->action(fn (Appointment $record) => self::mudarStatus($record, StatusAgendamento::Confirmado, 'Presença confirmada'));
    }

    public static function realizado(): Action
    {
        return Action::make('realizado')
            ->label('Chegou / realizado')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('primary')
            ->visible(fn (?Appointment $record): bool => self::podeMarcarComparecimento($record))
            ->action(fn (Appointment $record) => self::mudarStatus($record, StatusAgendamento::Realizado, 'Atendimento realizado'));
    }

    public static function faltou(): Action
    {
        return Action::make('faltou')
            ->label('Faltou')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (?Appointment $record): bool => self::podeMarcarComparecimento($record))
            ->requiresConfirmation()
            ->modalHeading('Marcar como falta?')
            ->modalDescription('O paciente não compareceu. No Kommo isso preencheria Comparecimento = Faltou e dispararia a régua de resgate (A5).')
            ->modalSubmitActionLabel('Marcar falta')
            ->action(fn (Appointment $record) => self::mudarStatus($record, StatusAgendamento::Faltou, 'Falta registrada'));
    }

    public static function cancelar(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->visible(fn (?Appointment $record): bool => $record?->status->emAberto() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Cancelar agendamento?')
            ->modalDescription('O horário fica livre na agenda. Para trocar de horário, use Remarcar.')
            ->modalSubmitActionLabel('Cancelar agendamento')
            ->modalCancelActionLabel('Voltar')
            ->action(fn (Appointment $record) => self::mudarStatus($record, StatusAgendamento::Cancelado, 'Agendamento cancelado'));
    }

    public static function remarcar(): Action
    {
        return Action::make('remarcar')
            ->label('Remarcar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (?Appointment $record): bool => $record?->status->emAberto() ?? false)
            ->modalHeading('Remarcar agendamento')
            ->modalDescription(fn (Appointment $record): string => sprintf(
                '%s — hoje em %s. O horário atual fica como "remarcado" e um novo agendamento é criado.',
                $record->patient->nome ?? 'Paciente',
                $record->inicioLocal()->format('d/m/Y \à\s H:i'),
            ))
            ->modalSubmitActionLabel('Remarcar')
            ->schema(fn (Appointment $record): array => [
                DatePicker::make('data')
                    ->label('Nova data')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->firstDayOfWeek(1)
                    ->required()
                    ->live(),
                Select::make('hora')
                    ->label('Novo horário')
                    ->options(AppointmentForm::horarios())
                    ->required()
                    ->rules([AppointmentForm::regraDeConflito((int) $record->doctor_id, (int) $record->getKey())]),
                TextInput::make('duracao_min')
                    ->label('Duração')
                    ->numeric()
                    ->integer()
                    ->minValue(10)
                    ->maxValue(720)
                    ->suffix('min')
                    ->required(),
            ])
            ->fillForm(fn (Appointment $record): array => AppointmentForm::doAgendamento($record))
            ->action(function (Appointment $record, array $data, Action $action): void {
                $intervalo = AppointmentForm::intervalo($data['data'] ?? null, $data['hora'] ?? null, $data['duracao_min'] ?? null);

                try {
                    if ($intervalo === null) {
                        throw new DomainException('Informe a nova data, o horário e a duração.');
                    }

                    $resultado = app(AgendaService::class)->remarcar($record, $intervalo[0], (int) $data['duracao_min']);
                } catch (ConflitoDeHorario|DomainException $e) {
                    Notification::make()->danger()->title('Não foi possível remarcar')->body(e($e->getMessage()))->send();
                    $action->halt();

                    return;
                }

                AvisoDaAgenda::enviar($resultado, 'Agendamento remarcado');
            });
    }

    public static function podeMarcarComparecimento(?Appointment $record): bool
    {
        return $record !== null
            && $record->status->emAberto()
            && ! $record->inicioLocal()->startOfDay()->isAfter(CarbonImmutable::now(Appointment::fuso()));
    }

    private static function mudarStatus(Appointment $record, StatusAgendamento $status, string $titulo): void
    {
        try {
            $resultado = app(AgendaService::class)->mudarStatus($record, $status);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Não foi possível')->body(e($e->getMessage()))->send();

            return;
        }

        AvisoDaAgenda::enviar($resultado, $titulo);
    }
}
