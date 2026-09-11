<?php

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Database\Seeders\AgendaDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Agenda\Cenario;

it('roda duas vezes sem duplicar e sem horários sobrepostos', function () {
    // Agendamento real (demo = false) num domingo: o seeder não pode apagar.
    $eduardo = Cenario::eduardo();
    $real = Cenario::agendamento(
        $eduardo,
        Cenario::paciente($eduardo, 'Paciente Real'),
        Cenario::domingoDestaSemana(),
    );

    $this->seed(AgendaDemoSeeder::class);

    $primeira = Appointment::query()->where('demo', true)->count();
    $pacientes = Patient::query()->where('demo', true)->count();

    $this->seed(AgendaDemoSeeder::class);

    expect($primeira)->toBeGreaterThan(300)
        ->and(Appointment::query()->where('demo', true)->count())->toBe($primeira)
        ->and(Patient::query()->where('demo', true)->count())->toBe($pacientes)
        ->and(Appointment::query()->whereKey($real->id)->exists())->toBeTrue();

    $sobrepostos = DB::table('appointments as a')
        ->join('appointments as b', function ($join) {
            $join->on('a.doctor_id', '=', 'b.doctor_id')
                ->on('a.id', '<', 'b.id')
                ->on('a.inicio', '<', 'b.fim')
                ->on('a.fim', '>', 'b.inicio');
        })
        ->count();

    expect($sobrepostos)->toBe(0);
});

it('gera a mistura esperada de status e tipos', function () {
    $this->seed(AgendaDemoSeeder::class);

    $agora = now();

    $passados = Appointment::query()->where('fim', '<', $agora)->pluck('status');
    $futuros = Appointment::query()->where('inicio', '>', $agora)->pluck('status')->unique();

    $realizados = $passados->filter(fn ($s) => $s === StatusAgendamento::Realizado)->count() / $passados->count();

    expect($realizados)->toBeGreaterThan(0.6)->toBeLessThan(0.9)
        ->and($passados->contains(StatusAgendamento::Faltou))->toBeTrue()
        ->and($futuros->every(fn ($s) => in_array($s, [StatusAgendamento::Agendado, StatusAgendamento::Confirmado], true)))->toBeTrue();

    $cirurgias = Appointment::query()->where('tipo', TipoAgendamento::Cirurgia)->with('doctor')->get();

    expect($cirurgias)->not->toBeEmpty()
        ->and($cirurgias->every(fn (Appointment $a) => $a->doctor->agente === 'duda' && $a->sala === 'Centro cirúrgico'))->toBeTrue()
        ->and($cirurgias->every(fn (Appointment $a) => $a->duracaoMin() >= 120))->toBeTrue();

    foreach (TipoAgendamento::cases() as $tipo) {
        expect(Appointment::query()->where('tipo', $tipo)->exists())->toBeTrue();
    }

    // Sem domingo e nada fora do horário da clínica.
    $foraDoHorario = Appointment::query()->get()->filter(function (Appointment $a): bool {
        $inicio = $a->inicioLocal();
        $fim = $a->fimLocal();

        return $inicio->isSunday() || $inicio->hour < 7 || ($fim->hour * 60 + $fim->minute) > 18 * 60;
    });

    expect($foraDoHorario)->toBeEmpty()
        ->and(Appointment::query()->whereNotNull('kommo_lead_id')->exists())->toBeTrue();
});
