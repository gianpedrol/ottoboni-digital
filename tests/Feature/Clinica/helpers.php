<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;

/**
 * @return array{0: Doctor, 1: Doctor}
 */
function clinicaMedicos(): array
{
    $eduardo = Doctor::query()->firstOrCreate(
        ['agente' => 'duda'],
        ['nome' => 'Dr. Eduardo Ottoboni', 'kommo_pipeline_id' => config('kommo.pipelines.duda'), 'ativo' => true],
    );

    $vanessa = Doctor::query()->firstOrCreate(
        ['agente' => 'luna'],
        ['nome' => 'Dra. Vanessa Ottoboni', 'kommo_pipeline_id' => config('kommo.pipelines.luna'), 'ativo' => true],
    );

    return [$eduardo, $vanessa];
}

function clinicaUsuario(UserRole $role, DoctorScope $scope = DoctorScope::Ambos): User
{
    return User::query()->create([
        'name' => 'Usuário ' . $role->getLabel(),
        'email' => uniqid('clinica') . '@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => $role,
        'doctor_scope' => $scope,
    ]);
}

/**
 * @param  array<string, mixed>  $atributos
 */
function clinicaPaciente(Doctor $doctor, array $atributos = []): Patient
{
    static $sequencia = 0;
    $sequencia++;

    return Patient::query()->create([
        'nome' => "Paciente Teste {$sequencia}",
        'telefone' => sprintf('+55 41 9%04d-%04d', 7000 + $sequencia, 1000 + $sequencia),
        'doctor_id' => $doctor->id,
        ...$atributos,
    ]);
}
