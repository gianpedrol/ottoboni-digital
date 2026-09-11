<?php

namespace App\Services\Agenda;

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regras da agenda própria: conflito de horário, mudança de status e
 * remarcação. Toda tela passa por aqui (página Agenda, resource e testes).
 */
class AgendaService
{
    public function __construct(private readonly EfeitosNoKommo $efeitos) {}

    /**
     * @param  array{patient_id: int|string, doctor_id: int|string, service_id?: int|string|null, tipo: TipoAgendamento|string, inicio: CarbonInterface, fim: CarbonInterface, sala?: ?string, observacoes?: ?string, demo?: bool}  $dados
     */
    public function agendar(array $dados): ResultadoDaAgenda
    {
        $inicio = Appointment::paraBanco($dados['inicio']);
        $fim = Appointment::paraBanco($dados['fim']);

        $this->validarIntervalo($inicio, $fim);
        $this->garantirSemConflito((int) $dados['doctor_id'], $inicio, $fim);

        $paciente = Patient::query()->find($dados['patient_id']);

        $agendamento = Appointment::query()->create([
            'patient_id' => (int) $dados['patient_id'],
            'doctor_id' => (int) $dados['doctor_id'],
            'service_id' => filled($dados['service_id'] ?? null) ? (int) $dados['service_id'] : null,
            'tipo' => $dados['tipo'],
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => StatusAgendamento::Agendado,
            'sala' => $dados['sala'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'kommo_lead_id' => $paciente?->kommo_lead_id,
            'demo' => $dados['demo'] ?? false,
        ]);

        $efeito = $this->efeitos->aoAgendar($agendamento);
        $this->efeitos->registrar($efeito);

        return new ResultadoDaAgenda($agendamento, $efeito);
    }

    /**
     * Edição pelo formulário. Se o horário mudou num agendamento em aberto,
     * a Próxima consulta do Kommo também mudaria.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(Appointment $agendamento, array $dados): ResultadoDaAgenda
    {
        $inicio = Appointment::paraBanco($dados['inicio']);
        $fim = Appointment::paraBanco($dados['fim']);

        $this->validarIntervalo($inicio, $fim);
        $this->garantirSemConflito((int) $dados['doctor_id'], $inicio, $fim, (int) $agendamento->getKey());

        $mudouHorario = ! $agendamento->inicio->equalTo($inicio);

        $agendamento->fill([
            ...$dados,
            'inicio' => $inicio,
            'fim' => $fim,
        ]);

        if ($agendamento->isDirty('patient_id')) {
            $agendamento->kommo_lead_id = Patient::query()->whereKey($agendamento->patient_id)->value('kommo_lead_id');
        }

        $agendamento->save();

        $efeito = null;

        if ($mudouHorario && $agendamento->status->emAberto()) {
            $efeito = $this->efeitos->aoAgendar($agendamento);
            $this->efeitos->registrar($efeito);
        }

        return new ResultadoDaAgenda($agendamento, $efeito);
    }

    public function mudarStatus(Appointment $agendamento, StatusAgendamento $status): ResultadoDaAgenda
    {
        if ($status === StatusAgendamento::Remarcado) {
            throw new DomainException('Para remarcar, escolha o novo horário (ação Remarcar).');
        }

        if (! $agendamento->status->emAberto()) {
            throw new DomainException("Este agendamento já está como \"{$agendamento->status->getLabel()}\".");
        }

        $comparecimento = in_array($status, [StatusAgendamento::Realizado, StatusAgendamento::Faltou], true);

        if ($comparecimento && $agendamento->inicioLocal()->startOfDay()->isAfter(CarbonImmutable::now(Appointment::fuso()))) {
            throw new DomainException('Comparecimento só pode ser marcado no dia do agendamento ou depois.');
        }

        $agendamento->update(['status' => $status]);

        $efeito = $this->efeitos->aoMudarStatus($agendamento, $status);

        if ($efeito !== null) {
            $this->efeitos->registrar($efeito);
        }

        return new ResultadoDaAgenda($agendamento, $efeito);
    }

    /**
     * Marca o antigo como remarcado e cria o novo com os mesmos dados.
     */
    public function remarcar(Appointment $antigo, CarbonInterface $novoInicio, ?int $duracaoMin = null): ResultadoDaAgenda
    {
        if (! $antigo->status->emAberto()) {
            throw new DomainException("Só dá para remarcar agendamento em aberto (este está \"{$antigo->status->getLabel()}\").");
        }

        $duracaoMin ??= $antigo->duracaoMin();

        $inicio = Appointment::paraBanco($novoInicio);
        $fim = $inicio->addMinutes($duracaoMin);

        $this->validarIntervalo($inicio, $fim);
        $this->garantirSemConflito((int) $antigo->doctor_id, $inicio, $fim, (int) $antigo->getKey());

        $novo = DB::transaction(function () use ($antigo, $inicio, $fim): Appointment {
            $antigo->update(['status' => StatusAgendamento::Remarcado]);

            $nota = 'Remarcado de ' . $antigo->inicioLocal()->format('d/m/Y H:i') . '.';

            $novo = $antigo->replicate(['created_at', 'updated_at']);
            $novo->fill([
                'inicio' => $inicio,
                'fim' => $fim,
                'status' => StatusAgendamento::Agendado,
                'observacoes' => trim($nota . ' ' . (string) $antigo->observacoes),
            ]);
            $novo->save();

            return $novo;
        });

        $efeito = $this->efeitos->aoRemarcar($novo, $antigo);
        $this->efeitos->registrar($efeito);

        return new ResultadoDaAgenda($novo, $efeito, $antigo->refresh());
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function conflitos(int $doctorId, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarId = null): Collection
    {
        return Appointment::query()
            ->where('doctor_id', $doctorId)
            ->ocupandoHorario()
            ->sobrepondo($inicio, $fim)
            ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
            ->orderBy('inicio')
            ->get();
    }

    public function garantirSemConflito(int $doctorId, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarId = null): void
    {
        $existente = $this->conflitos($doctorId, $inicio, $fim, $ignorarId)->first();

        if ($existente !== null) {
            throw new ConflitoDeHorario($existente);
        }
    }

    private function validarIntervalo(CarbonInterface $inicio, CarbonInterface $fim): void
    {
        if (! $fim->isAfter($inicio)) {
            throw new DomainException('O fim do agendamento precisa ser depois do início.');
        }
    }
}
