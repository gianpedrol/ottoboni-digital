<?php

namespace Database\Seeders;

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Agenda fictícia: 8 semanas passadas + a atual + 4 futuras, segunda a
 * sábado (sábado só de manhã). Apaga demo = true e recria, então pode rodar
 * de novo sem duplicar. Os horários de cada médico nunca se sobrepõem.
 *
 * php artisan db:seed --class=AgendaDemoSeeder
 */
class AgendaDemoSeeder extends Seeder
{
    private const SERVICOS = [
        'duda' => [
            ['Consulta de cirurgia plástica', 'consulta', 600, 60],
            ['Retorno pós-operatório', 'retorno', 0, 30],
            ['Drenagem e curativo pós-operatório', 'procedimento', 250, 30],
            ['Pequena cirurgia ambulatorial', 'procedimento', 1800, 60],
            ['Rinoplastia', 'cirurgia', 18000, 180],
            ['Mamoplastia de aumento', 'cirurgia', 16000, 150],
            ['Abdominoplastia', 'cirurgia', 20000, 240],
            ['Lipoaspiração', 'cirurgia', 15000, 180],
        ],
        'luna' => [
            ['Consulta dermatológica', 'consulta', 450, 30],
            ['Retorno dermatológico', 'retorno', 0, 30],
            ['Toxina botulínica', 'procedimento', 1500, 30],
            ['Preenchimento com ácido hialurônico', 'procedimento', 2200, 60],
            ['Bioestimulador de colágeno', 'procedimento', 2800, 60],
            ['Laser / luz pulsada', 'procedimento', 900, 45],
            ['Peeling químico', 'procedimento', 600, 30],
        ],
    ];

    private const NOMES = [
        'Ana Beatriz Lima', 'Juliana Martins', 'Camila Rocha', 'Fernanda Alves', 'Patrícia Gomes',
        'Mariana Ribeiro', 'Letícia Carvalho', 'Aline Ferreira', 'Bruna Oliveira', 'Carolina Souza',
        'Débora Santos', 'Gabriela Pereira', 'Helena Costa', 'Isabela Mendes', 'Larissa Barbosa',
        'Luana Teixeira', 'Natália Araújo', 'Priscila Cardoso', 'Renata Moreira', 'Sabrina Nunes',
        'Tatiane Dias', 'Vanessa Castro', 'Viviane Pinto', 'Yasmin Duarte', 'Rodrigo Almeida',
        'Marcelo Freitas', 'Thiago Cunha', 'Rafael Monteiro', 'Cláudia Ramos', 'Simone Vieira',
        'Elaine Correia', 'Adriana Lopes', 'Beatriz Nogueira', 'Daniela Batista', 'Paula Machado',
        'Roberta Farias',
    ];

    private const OBSERVACOES = [
        'Primeira consulta — veio por indicação.',
        'Trazer exames recentes.',
        'Prefere contato por WhatsApp.',
        'Chegar 15 minutos antes para o cadastro.',
        'Paciente pediu horário no fim da tarde.',
        'Avaliar fotos do pré-operatório.',
    ];

    private Randomizer $rng;

    public function run(): void
    {
        $this->rng = new Randomizer(new Mt19937(20260910));

        $medicos = collect([
            $this->medico('duda', 'Dr. Eduardo Ottoboni'),
            $this->medico('luna', 'Dra. Vanessa Ottoboni'),
        ]);

        Appointment::query()->where('demo', true)->delete();

        $pacientes = $this->pacientesPorMedico($medicos);
        $servicos = $medicos->mapWithKeys(fn (Doctor $m): array => [$m->id => $this->servicos($m)]);

        $fuso = Appointment::fuso();
        $agora = CarbonImmutable::now($fuso);
        $segunda = $agora->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $primeiroDia = $segunda->subWeeks(8);
        $ultimoDia = $segunda->addWeeks(4)->addDays(5);

        $linhas = [];

        for ($dia = $primeiroDia; $dia->lte($ultimoDia); $dia = $dia->addDay()) {
            if ($dia->isSunday()) {
                continue;
            }

            foreach ($medicos as $medico) {
                foreach ($this->diaDoMedico($medico, $dia, $servicos[$medico->id]) as $item) {
                    $paciente = $this->sortear($pacientes[$medico->id]);

                    $linhas[] = [
                        'patient_id' => $paciente->id,
                        'doctor_id' => $medico->id,
                        'service_id' => $item['servico']?->id,
                        'tipo' => $item['tipo']->value,
                        'inicio' => Appointment::paraBanco($item['inicio'])->format('Y-m-d H:i:s'),
                        'fim' => Appointment::paraBanco($item['fim'])->format('Y-m-d H:i:s'),
                        'status' => $this->status($item['inicio'], $item['fim'], $agora)->value,
                        'sala' => $item['sala'],
                        'observacoes' => $this->rng->getInt(1, 100) <= 15 ? $this->sortear(self::OBSERVACOES) : null,
                        'kommo_lead_id' => $paciente->kommo_lead_id,
                        'demo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($linhas, 250) as $lote) {
            Appointment::query()->insert($lote);
        }

        $this->command?->info(count($linhas) . ' agendamentos de demonstração criados.');
    }

    private function medico(string $agente, string $nome): Doctor
    {
        return Doctor::query()->firstOrCreate(
            ['agente' => $agente],
            ['nome' => $nome, 'kommo_pipeline_id' => config("kommo.pipelines.{$agente}"), 'ativo' => true],
        );
    }

    /**
     * @param  Collection<int, Doctor>  $medicos
     * @return array<int, array<int, Patient>>
     */
    private function pacientesPorMedico(Collection $medicos): array
    {
        $demo = Patient::query()->where('demo', true)->orderBy('id')->get();

        if ($demo->isEmpty()) {
            $demo = $this->criarPacientes($medicos);
        }

        $porMedico = [];

        foreach ($medicos as $i => $medico) {
            $proprios = $demo->where('doctor_id', $medico->id)->values();

            // Sem médico definido: divide entre os dois.
            $semMedico = $demo->whereNull('doctor_id')->values()
                ->filter(fn (Patient $p, int $k): bool => $k % $medicos->count() === $i);

            $lista = $proprios->concat($semMedico)->values();

            $porMedico[$medico->id] = ($lista->isNotEmpty() ? $lista : $demo)->all();
        }

        return $porMedico;
    }

    /**
     * Mínimo para o seeder funcionar sozinho (sem o seeder de Pacientes).
     *
     * @param  Collection<int, Doctor>  $medicos
     * @return Collection<int, Patient>
     */
    private function criarPacientes(Collection $medicos): Collection
    {
        $criados = collect();

        // Sorteio próprio: não altera a sequência da agenda entre uma rodada e outra.
        $rng = new Randomizer(new Mt19937(15));

        foreach (self::NOMES as $i => $nome) {
            $medico = $medicos[$i % $medicos->count()];

            $criados->push(Patient::query()->create([
                'nome' => $nome,
                'telefone' => sprintf('1599%03d%04d', $rng->getInt(100, 999), $rng->getInt(0, 9999)),
                'doctor_id' => $medico->id,
                'origem' => $i % 3 === 0 ? 'Indicação' : 'Instagram',
                'kommo_lead_id' => $i % 4 === 3 ? null : 31000000 + $i,
                'demo' => true,
            ]));
        }

        return $criados;
    }

    /**
     * @return Collection<int, Service>
     */
    private function servicos(Doctor $medico): Collection
    {
        $servicos = Service::query()
            ->where('ativo', true)
            ->where('doctor_id', $medico->id)
            ->get();

        if ($servicos->isNotEmpty()) {
            return $servicos;
        }

        foreach (self::SERVICOS[$medico->agente] ?? [] as [$nome, $tipo, $valor, $duracao]) {
            $servicos->push(Service::query()->create([
                'doctor_id' => $medico->id,
                'nome' => $nome,
                'tipo' => $tipo,
                'valor' => $valor,
                'duracao_min' => $duracao,
                'repasse_pct' => 0,
                'ativo' => true,
                'demo' => true,
            ]));
        }

        return $servicos;
    }

    /**
     * Horários de um médico num dia, em sequência (nunca se sobrepõem).
     *
     * @param  Collection<int, Service>  $servicos
     * @return array<int, array{servico: ?Service, tipo: TipoAgendamento, inicio: CarbonImmutable, fim: CarbonImmutable, sala: ?string}>
     */
    private function diaDoMedico(Doctor $medico, CarbonImmutable $dia, Collection $servicos): array
    {
        $sabado = $dia->isSaturday();
        $fecha = $sabado ? $dia->setTime(12, 0) : $dia->setTime(18, 0);
        $almoco = [$dia->setTime(12, 0), $dia->setTime(13, 0)];
        $quantidade = $sabado ? $this->rng->getInt(3, 5) : $this->rng->getInt(5, 9);

        $itens = [];
        $cursor = $dia->setTime(8, 0);

        // Cirurgias do Dr. Eduardo: bloco longo de manhã, terças e algumas quintas.
        $chanceCirurgia = match ($dia->dayOfWeekIso) {
            2 => 80,
            4 => 40,
            default => 0,
        };

        $cirurgias = $servicos->where('tipo', TipoAgendamento::Cirurgia->value)->values();

        if ($medico->agente === 'duda' && $cirurgias->isNotEmpty() && $this->rng->getInt(1, 100) <= $chanceCirurgia) {
            $servico = $this->sortear($cirurgias->all());
            $inicio = $dia->setTime(7, 0);
            $fim = $inicio->addMinutes(min(300, max(120, (int) $servico->duracao_min)));

            $itens[] = ['servico' => $servico, 'tipo' => TipoAgendamento::Cirurgia, 'inicio' => $inicio, 'fim' => $fim, 'sala' => 'Centro cirúrgico'];

            $cursor = $this->arredondar($fim->addMinutes(60));
            $quantidade = max(3, $quantidade - 3);
        }

        $total = count($itens) + $quantidade;
        $tentativas = 0;

        while (count($itens) < $total && $tentativas++ < 40) {
            $cursor = $cursor->addMinutes($this->sortear([0, 0, 0, 30, 30, 60]));

            [$tipo, $servico] = $this->sortearTipo($medico, $servicos);
            $duracao = $tipo === TipoAgendamento::Online ? 30 : max(15, (int) ($servico->duracao_min ?? 30));

            if (! $sabado && $cursor->lt($almoco[1]) && $cursor->addMinutes($duracao)->gt($almoco[0])) {
                $cursor = $almoco[1];
            }

            if ($cursor->addMinutes($duracao)->gt($fecha)) {
                break;
            }

            $itens[] = [
                'servico' => $servico,
                'tipo' => $tipo,
                'inicio' => $cursor,
                'fim' => $cursor->addMinutes($duracao),
                'sala' => $this->sala($medico, $tipo),
            ];

            $cursor = $cursor->addMinutes($duracao);
        }

        return $itens;
    }

    /**
     * @param  Collection<int, Service>  $servicos
     * @return array{0: TipoAgendamento, 1: ?Service}
     */
    private function sortearTipo(Doctor $medico, Collection $servicos): array
    {
        $pesos = $medico->agente === 'duda'
            ? ['consulta' => 40, 'retorno' => 30, 'online' => 15, 'procedimento' => 15]
            : ['consulta' => 30, 'retorno' => 20, 'online' => 10, 'procedimento' => 40];

        $sorteio = $this->rng->getInt(1, array_sum($pesos));
        $tipo = TipoAgendamento::Consulta;

        foreach ($pesos as $valor => $peso) {
            $sorteio -= $peso;

            if ($sorteio <= 0) {
                $tipo = TipoAgendamento::from($valor);
                break;
            }
        }

        $tipoServico = $tipo === TipoAgendamento::Online ? 'consulta' : $tipo->value;
        $candidatos = $servicos->where('tipo', $tipoServico)->values();

        if ($candidatos->isEmpty()) {
            $candidatos = $servicos->where('tipo', '!=', 'cirurgia')->values();
        }

        return [$tipo, $candidatos->isNotEmpty() ? $this->sortear($candidatos->all()) : null];
    }

    private function sala(Doctor $medico, TipoAgendamento $tipo): string
    {
        return match ($tipo) {
            TipoAgendamento::Online => 'Online',
            TipoAgendamento::Cirurgia => 'Centro cirúrgico',
            TipoAgendamento::Procedimento => $medico->agente === 'duda' ? 'Sala de procedimentos' : 'Sala de estética',
            default => $medico->agente === 'duda' ? 'Consultório 1' : 'Consultório 2',
        };
    }

    private function status(CarbonImmutable $inicio, CarbonImmutable $fim, CarbonImmutable $agora): StatusAgendamento
    {
        if ($fim->lte($agora)) {
            $sorteio = $this->rng->getInt(1, 100);

            return match (true) {
                $sorteio <= 75 => StatusAgendamento::Realizado,
                $sorteio <= 87 => StatusAgendamento::Faltou,
                $sorteio <= 94 => StatusAgendamento::Cancelado,
                default => StatusAgendamento::Remarcado,
            };
        }

        if ($inicio->lte($agora)) {
            return StatusAgendamento::Confirmado;
        }

        $chanceConfirmado = $inicio->lte($agora->addDays(2)) ? 60 : 20;

        return $this->rng->getInt(1, 100) <= $chanceConfirmado
            ? StatusAgendamento::Confirmado
            : StatusAgendamento::Agendado;
    }

    private function arredondar(CarbonImmutable $hora): CarbonImmutable
    {
        $resto = $hora->minute % 30;

        return $resto === 0 ? $hora : $hora->addMinutes(30 - $resto);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $itens
     * @return T
     */
    private function sortear(array $itens): mixed
    {
        return $itens[$this->rng->getInt(0, count($itens) - 1)];
    }
}
