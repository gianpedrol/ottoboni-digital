<?php

namespace Tests\Feature\Agenda;

use App\Enums\DoctorScope;
use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Montagem dos cenários dos testes da agenda.
 */
final class Cenario
{
    public static function eduardo(): Doctor
    {
        return Doctor::query()->firstOrCreate(
            ['agente' => 'duda'],
            ['nome' => 'Dr. Eduardo Ottoboni', 'kommo_pipeline_id' => config('kommo.pipelines.duda'), 'ativo' => true],
        );
    }

    public static function vanessa(): Doctor
    {
        return Doctor::query()->firstOrCreate(
            ['agente' => 'luna'],
            ['nome' => 'Dra. Vanessa Ottoboni', 'kommo_pipeline_id' => config('kommo.pipelines.luna'), 'ativo' => true],
        );
    }

    public static function usuario(UserRole $role, DoctorScope $scope = DoctorScope::Ambos): User
    {
        return User::query()->create([
            'name' => 'Teste agenda',
            'email' => uniqid('agenda') . '@ottoboni.local',
            'password' => 'senha-de-teste',
            'role' => $role,
            'doctor_scope' => $scope,
        ]);
    }

    public static function paciente(Doctor $medico, string $nome = 'Maria Teste', ?int $leadId = 777001): Patient
    {
        return Patient::query()->create([
            'nome' => $nome,
            'telefone' => '15999990000',
            'doctor_id' => $medico->id,
            'kommo_lead_id' => $leadId,
        ]);
    }

    public static function agendamento(
        Doctor $medico,
        Patient $paciente,
        CarbonImmutable $inicioLocal,
        int $minutos = 30,
        StatusAgendamento $status = StatusAgendamento::Agendado,
        TipoAgendamento $tipo = TipoAgendamento::Consulta,
    ): Appointment {
        return Appointment::query()->create([
            'patient_id' => $paciente->id,
            'doctor_id' => $medico->id,
            'tipo' => $tipo,
            'inicio' => Appointment::paraBanco($inicioLocal),
            'fim' => Appointment::paraBanco($inicioLocal->addMinutes($minutos)),
            'status' => $status,
            'kommo_lead_id' => $paciente->kommo_lead_id,
        ]);
    }

    /**
     * Terça-feira da semana atual, no fuso da clínica (sempre aparece na
     * visão semana, que vai de segunda a sábado).
     */
    public static function tercaDestaSemana(int $hora = 10, int $minuto = 0): CarbonImmutable
    {
        return self::segundaDestaSemana()->addDay()->setTime($hora, $minuto);
    }

    /**
     * Um dia útil futuro, longe de qualquer borda de semana.
     */
    public static function segundaQueVem(int $hora = 10, int $minuto = 0): CarbonImmutable
    {
        return self::segundaDestaSemana()->addWeek()->setTime($hora, $minuto);
    }

    public static function domingoDestaSemana(int $hora = 10): CarbonImmutable
    {
        return self::segundaDestaSemana()->addDays(6)->setTime($hora, 0);
    }

    // No Carbon 3 o início da semana depende do locale; a agenda é sempre segunda.
    private static function segundaDestaSemana(): CarbonImmutable
    {
        return CarbonImmutable::now(Appointment::fuso())->startOfWeek(CarbonInterface::MONDAY);
    }
}
