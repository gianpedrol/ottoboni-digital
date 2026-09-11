<?php

use App\Enums\KommoSyncStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Support\Cpf;
use Database\Seeders\PacientesDemoSeeder;
use Database\Seeders\ProntuarioDemoSeeder;

require_once __DIR__ . '/helpers.php';

it('seeders de pacientes e prontuário rodam duas vezes sem duplicar', function () {
    $admin = clinicaUsuario(UserRole::Admin);

    $this->seed(PacientesDemoSeeder::class);
    $this->seed(ProntuarioDemoSeeder::class);

    $registros = MedicalRecord::query()->where('demo', true)->count();

    $this->seed(PacientesDemoSeeder::class);
    $this->seed(ProntuarioDemoSeeder::class);

    $pacientes = Patient::query()->where('demo', true)->get();

    expect($pacientes)->toHaveCount(40)
        ->and(Doctor::query()->count())->toBe(2)
        ->and(MedicalRecord::query()->where('demo', true)->count())->toBe($registros)
        ->and($registros)->toBeGreaterThan(40)
        ->and($pacientes->pluck('telefone')->unique())->toHaveCount(40)
        ->and($pacientes->filter(fn (Patient $p) => $p->cpf !== null)->every(fn (Patient $p) => Cpf::valido($p->cpf)))->toBeTrue()
        ->and($pacientes->where('kommo_sync_status', KommoSyncStatus::Sincronizado))->toHaveCount(24)
        ->and($pacientes->where('kommo_sync_status', KommoSyncStatus::Simulado))->toHaveCount(12)
        ->and($pacientes->where('kommo_sync_status', KommoSyncStatus::Pendente))->toHaveCount(4)
        ->and($pacientes->groupBy('doctor_id'))->toHaveCount(2);

    $assinados = MedicalRecord::query()->where('demo', true)->whereNotNull('assinado_em')->count();

    expect($assinados)->toBeGreaterThan($registros / 2)
        ->and(MedicalRecord::query()->where('demo', true)->distinct()->pluck('user_id')->all())->toBe([$admin->id])
        ->and(MedicalRecord::query()->where('demo', true)->distinct()->count('patient_id'))->toBe(15);
});
