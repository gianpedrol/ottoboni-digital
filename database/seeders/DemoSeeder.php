<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dados fictícios do protótipo da Fase 3.
 * php artisan db:seed --class=DemoSeeder
 *
 * Cada seeder apaga só as linhas com demo = true antes de recriar, então
 * pode rodar quantas vezes quiser. A ordem importa (chaves estrangeiras).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,        // médicos e admin
            PacientesDemoSeeder::class,
            FinanceiroDemoSeeder::class,  // serviços, receber, pagar, extrato
            AgendaDemoSeeder::class,      // usa pacientes e serviços
            ProntuarioDemoSeeder::class,  // usa pacientes e agenda
            AutomacoesDemoSeeder::class,
        ]);
    }
}
