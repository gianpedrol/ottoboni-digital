<?php

namespace Database\Seeders;

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Doctor::query()->updateOrCreate(
            ['agente' => 'duda'],
            [
                'nome' => 'Dr. Eduardo Ottoboni',
                'kommo_pipeline_id' => config('kommo.pipelines.duda'),
                'ativo' => true,
            ],
        );

        Doctor::query()->updateOrCreate(
            ['agente' => 'luna'],
            [
                'nome' => 'Dra. Vanessa Ottoboni',
                'kommo_pipeline_id' => config('kommo.pipelines.luna'),
                'ativo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@ottoboni.local'],
            [
                'name' => 'Administrador',
                'password' => env('SEED_ADMIN_PASSWORD', 'trocar-esta-senha'),
                'role' => UserRole::Admin,
                'doctor_scope' => DoctorScope::Ambos,
            ],
        );
    }
}
