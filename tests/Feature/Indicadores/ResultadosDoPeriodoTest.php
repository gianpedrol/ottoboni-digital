<?php

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Receivable;
use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use App\Services\Indicadores\ResultadosDoPeriodo;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\KommoException;
use Carbon\CarbonImmutable;

const TZ_CLINICA = 'America/Sao_Paulo';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00', TZ_CLINICA));

    $this->duda = Doctor::query()->create([
        'nome' => 'Dr. Eduardo', 'agente' => 'duda', 'kommo_pipeline_id' => config('kommo.pipelines.duda'), 'ativo' => true,
    ]);
    $this->luna = Doctor::query()->create([
        'nome' => 'Dra. Vanessa', 'agente' => 'luna', 'kommo_pipeline_id' => config('kommo.pipelines.luna'), 'ativo' => true,
    ]);
    $this->paciente = Patient::query()->create(['nome' => 'Ana', 'telefone' => '+5541999990000', 'doctor_id' => $this->duda->id]);

    // Período de 30 dias: 12/08 a 10/09. Anterior: 13/07 a 11/08.
    $this->de = CarbonImmutable::parse('2026-08-12', TZ_CLINICA);
    $this->ate = CarbonImmutable::parse('2026-09-10', TZ_CLINICA);
});

function resultadosAtendimento(Doctor $doctor, Patient $paciente, TipoAgendamento $tipo, StatusAgendamento $status, string $quando): void
{
    $inicio = CarbonImmutable::parse($quando, TZ_CLINICA);

    Appointment::query()->create([
        'patient_id' => $paciente->id,
        'doctor_id' => $doctor->id,
        'tipo' => $tipo,
        'status' => $status,
        'inicio' => Appointment::paraBanco($inicio),
        'fim' => Appointment::paraBanco($inicio->addHour()),
    ]);
}

function resultadosContrato(Doctor $doctor, float $valor, string $assinadoEm, string $origem = 'contrato'): void
{
    $receivable = Receivable::query()->create([
        'doctor_id' => $doctor->id, 'descricao' => 'Contrato', 'valor_total' => $valor,
        'forma_pagamento' => 'boleto', 'origem' => $origem,
    ]);

    $data = CarbonImmutable::parse($assinadoEm, TZ_CLINICA)->setTimezone(config('app.timezone'));
    $receivable->forceFill(['created_at' => $data])->save();
}

function resultadosLead(string $criadoEm): LeadData
{
    static $id = 0;

    return new LeadData(
        id: ++$id, name: "Lead {$id}", pipelineId: (int) config('kommo.pipelines.duda'), statusId: 1,
        responsibleUserId: null, createdAt: CarbonImmutable::parse($criadoEm, TZ_CLINICA)->utc(),
        updatedAt: null, closedAt: null, price: 0, origem: null, temperatura: null, procedimento: null,
        urgencia: null, score: null, instagram: null, contactIds: [], lossReason: null,
    );
}

/**
 * Repositório falso que devolve só os leads criados dentro do período pedido.
 *
 * @param  array<int, LeadData>  $leads
 */
function resultadosRepositorio(array $leads): LeadRepository
{
    $repo = Mockery::mock(LeadRepository::class);
    $repo->shouldReceive('leads')->andReturnUsing(fn (LeadFilters $f) => collect($leads)
        ->filter(fn (LeadData $l): bool => $l->createdAt->between($f->from, $f->to))
        ->values());

    return $repo;
}

it('conta consultas e cirurgias realizadas no período e compara com o anterior', function () {
    $p = $this->paciente;
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Consulta, StatusAgendamento::Realizado, '2026-08-20 09:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Retorno, StatusAgendamento::Realizado, '2026-09-01 10:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Online, StatusAgendamento::Realizado, '2026-09-09 14:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Consulta, StatusAgendamento::Faltou, '2026-09-02 11:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Consulta, StatusAgendamento::Cancelado, '2026-09-03 11:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Cirurgia, StatusAgendamento::Realizado, '2026-09-08 07:00');
    // Outro médico e período anterior
    resultadosAtendimento($this->luna, $p, TipoAgendamento::Consulta, StatusAgendamento::Realizado, '2026-09-04 09:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Consulta, StatusAgendamento::Realizado, '2026-07-20 09:00');
    resultadosAtendimento($this->duda, $p, TipoAgendamento::Consulta, StatusAgendamento::Realizado, '2026-08-11 18:00');

    $r = (new ResultadosDoPeriodo(resultadosRepositorio([])))
        ->calcular([(int) config('kommo.pipelines.duda')], $this->de, $this->ate);

    expect($r['consultas']['atual'])->toBe(3)
        ->and($r['consultas']['anterior'])->toBe(2)
        ->and($r['consultas']['variacao_pct'])->toBe(50.0)
        ->and($r['consultas']['faltas'])->toBe(1)
        ->and($r['consultas']['comparecimento_pct'])->toBe(75.0)
        ->and($r['consultas']['serie'])->toHaveCount(30)
        ->and(array_sum($r['consultas']['serie']))->toBe(3)
        ->and($r['cirurgias']['atual'])->toBe(1)
        ->and($r['cirurgias']['variacao_pct'])->toBeNull();
});

it('conta cirurgias agendadas para os próximos 30 dias', function () {
    resultadosAtendimento($this->duda, $this->paciente, TipoAgendamento::Cirurgia, StatusAgendamento::Confirmado, '2026-09-22 07:00');
    resultadosAtendimento($this->duda, $this->paciente, TipoAgendamento::Cirurgia, StatusAgendamento::Agendado, '2026-11-20 07:00');

    $r = (new ResultadosDoPeriodo(resultadosRepositorio([])))
        ->calcular([(int) config('kommo.pipelines.duda')], $this->de, $this->ate);

    expect($r['cirurgias']['proximas_30d'])->toBe(1);
});

it('novos contratos contam pela data de assinatura e somam o valor', function () {
    resultadosContrato($this->duda, 20000, '2026-08-25 10:00');
    resultadosContrato($this->duda, 20000, '2026-09-05 15:00');
    resultadosContrato($this->duda, 500, '2026-09-05 15:00', origem: 'consulta');
    resultadosContrato($this->duda, 18000, '2026-07-30 10:00');

    $r = (new ResultadosDoPeriodo(resultadosRepositorio([])))
        ->calcular([(int) config('kommo.pipelines.duda')], $this->de, $this->ate);

    expect($r['contratos']['atual'])->toBe(2)
        ->and($r['contratos']['valor'])->toBe(40000.0)
        ->and($r['contratos']['anterior'])->toBe(1)
        ->and($r['contratos']['variacao_pct'])->toBe(100.0);
});

it('leads vêm do Kommo e comparam com o período anterior', function () {
    $repo = resultadosRepositorio([
        resultadosLead('2026-08-15 10:00'), resultadosLead('2026-08-30 10:00'),
        resultadosLead('2026-09-09 10:00'), resultadosLead('2026-09-10 23:30'),
        resultadosLead('2026-07-20 10:00'), resultadosLead('2026-08-01 10:00'),
    ]);

    $r = (new ResultadosDoPeriodo($repo))->calcular([(int) config('kommo.pipelines.duda')], $this->de, $this->ate);

    expect($r['leads']['atual'])->toBe(4)
        ->and($r['leads']['anterior'])->toBe(2)
        ->and($r['leads']['variacao_pct'])->toBe(100.0);
});

it('Kommo fora do ar não derruba os outros números', function () {
    $repo = Mockery::mock(LeadRepository::class);
    $repo->shouldReceive('leads')->andThrow(new KommoException('fora do ar'));

    resultadosAtendimento($this->duda, $this->paciente, TipoAgendamento::Consulta, StatusAgendamento::Realizado, '2026-09-01 10:00');

    $r = (new ResultadosDoPeriodo($repo))->calcular([(int) config('kommo.pipelines.duda')], $this->de, $this->ate);

    expect($r['leads'])->toBeNull()
        ->and($r['consultas']['atual'])->toBe(1);
});

it('evolução semanal agrupa as últimas 12 semanas de segunda a domingo', function () {
    // 10/09/2026 é quinta: a última semana começa na segunda 07/09
    resultadosAtendimento($this->duda, $this->paciente, TipoAgendamento::Cirurgia, StatusAgendamento::Realizado, '2026-09-08 07:00');
    resultadosAtendimento($this->duda, $this->paciente, TipoAgendamento::Consulta, StatusAgendamento::Realizado, '2026-09-06 10:00');

    $e = (new ResultadosDoPeriodo(resultadosRepositorio([resultadosLead('2026-09-07 08:00')])))
        ->evolucaoSemanal([(int) config('kommo.pipelines.duda')]);

    expect($e['labels'])->toHaveCount(12)
        ->and(end($e['labels']))->toBe('07/09')
        ->and(end($e['cirurgias']))->toBe(1)
        ->and(end($e['consultas']))->toBe(0)
        ->and($e['consultas'][10])->toBe(1)
        ->and(end($e['leads']))->toBe(1);
});
