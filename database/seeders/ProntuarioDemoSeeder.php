<?php

namespace Database\Seeders;

use App\Enums\TipoRegistroProntuario;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Prontuário fictício para 15 pacientes demo: anamnese, 1–3 evoluções,
 * prescrição e fotos (e, para parte dos cirúrgicos, exames e atestado).
 * Liga o registro à consulta "realizado" demo do paciente quando houver.
 */
class ProntuarioDemoSeeder extends Seeder
{
    private const PACIENTES = 15;

    private ?User $autor = null;

    public function run(): void
    {
        mt_srand(4242);

        // Delete pelo query builder: não passa pela trava de registro assinado.
        MedicalRecord::query()->where('demo', true)->delete();

        $pacientes = $this->pacientes();

        if ($pacientes->isEmpty()) {
            $this->call(PacientesDemoSeeder::class);
            $pacientes = $this->pacientes();
        }

        $this->autor = User::query()->where('role', UserRole::Admin->value)->orderBy('id')->first();

        foreach ($pacientes->values() as $indice => $patient) {
            $this->prontuarioDe($patient, $indice);
        }
    }

    /**
     * @return Collection<int, Patient>
     */
    private function pacientes(): Collection
    {
        return Patient::query()->where('demo', true)->with('doctor')->orderBy('id')->limit(self::PACIENTES)->get();
    }

    private function prontuarioDe(Patient $patient, int $indice): void
    {
        $consultas = Appointment::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'realizado')
            ->where('demo', true)
            ->orderBy('inicio')
            ->get()
            ->values();

        $cirurgico = $patient->doctor?->agente === 'duda';
        $procedimento = $patient->procedimento_interesse ?? ($cirurgico ? 'cirurgia plástica' : 'tratamento estético');

        $base = $consultas->first()?->inicio ?? $patient->created_at?->copy()->addDays(mt_rand(2, 10))->setTime(10, 0) ?? now()->subMonth();
        $base = $this->noPassado($base);

        $this->registrar($patient, TipoRegistroProntuario::Anamnese, 'Anamnese inicial', $base, $consultas->get(0), [
            'dados' => $this->anamnese($indice, $cirurgico, $procedimento),
        ]);

        $this->registrar($patient, TipoRegistroProntuario::Prescricao, $cirurgico ? 'Prescrição pós-operatória' : 'Prescrição domiciliar', $base->copy()->addMinutes(25), $consultas->get(0), $this->prescricao($cirurgico));

        if ($cirurgico && $indice % 3 === 0) {
            $this->registrar($patient, TipoRegistroProntuario::Exame, 'Exames pré-operatórios', $base->copy()->addMinutes(30), $consultas->get(0), [
                'dados' => [
                    'exames' => ['Hemograma completo', 'Coagulograma', 'Glicemia de jejum', 'Eletrocardiograma', 'Beta-HCG'],
                    'indicacao_clinica' => "Avaliação pré-operatória para {$procedimento}.",
                ],
            ]);
        }

        $evolucoes = 1 + $indice % 3;
        $quando = $base->copy();

        for ($n = 0; $n < $evolucoes; $n++) {
            $quando = $this->noPassado($quando->copy()->addDays(mt_rand(7, 25)));
            $ultima = $n === $evolucoes - 1;

            $this->registrar(
                $patient,
                TipoRegistroProntuario::Evolucao,
                $cirurgico ? 'Evolução pós-operatória' : 'Evolução do tratamento',
                $quando,
                $consultas->get($n + 1),
                ['conteudo' => $this->evolucao($cirurgico, $n)],
                assinar: ! ($ultima && $indice % 4 === 3),
            );
        }

        if ($cirurgico && $indice % 4 === 0) {
            $this->registrar($patient, TipoRegistroProntuario::Atestado, 'Atestado de afastamento', $base->copy()->addDays(1), null, [
                'conteudo' => 'Atesto, para os devidos fins, que o(a) paciente foi submetido(a) a procedimento cirúrgico e necessita de afastamento de suas atividades pelo período indicado.',
                'dados' => ['dias' => 15, 'inicio' => $base->copy()->addDays(1)->toDateString(), 'cid' => null],
            ]);
        }

        if ($cirurgico || $indice % 2 === 1) {
            $this->registrar($patient, TipoRegistroProntuario::Foto, 'Registro fotográfico', $base->copy()->addMinutes(40), $consultas->get(0), [
                'anexos' => $this->fotos($indice, $evolucoes >= 2),
                'conteudo' => 'Fotos com fundo neutro e mesma iluminação.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    private function registrar(Patient $patient, TipoRegistroProntuario $tipo, string $titulo, Carbon $quando, ?Appointment $consulta, array $campos, bool $assinar = true): void
    {
        $record = new MedicalRecord([
            ...$campos,
            'patient_id' => $patient->id,
            'doctor_id' => $consulta->doctor_id ?? $patient->doctor_id,
            'appointment_id' => $consulta?->id,
            'user_id' => $this->autor?->id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'assinado_em' => $assinar ? $this->noPassado($quando->copy()->addMinutes(50)) : null,
            'demo' => true,
        ]);
        $record->created_at = $quando;
        $record->updated_at = $quando;
        $record->save();
    }

    private function noPassado(Carbon $data): Carbon
    {
        return $data->isFuture() ? now()->subHours(mt_rand(2, 48)) : $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function anamnese(int $indice, bool $cirurgico, string $procedimento): array
    {
        $peso = mt_rand(52, 86);
        $altura = mt_rand(155, 182) / 100;

        return [
            'queixa_principal' => $cirurgico
                ? "Deseja realizar {$procedimento}. Refere incômodo estético há anos."
                : "Interesse em {$procedimento}. Incomoda-se com o aspecto da pele do rosto.",
            'historia_doenca_atual' => $cirurgico
                ? 'Procura avaliação cirúrgica após pesquisar sobre o procedimento. Peso estável nos últimos 12 meses. Nega intercorrências em procedimentos anteriores.'
                : 'Queixa progressiva nos últimos 2 anos, com piora após exposição solar. Já usou cosméticos sem orientação médica.',
            'antecedentes' => ['Nega HAS e DM.', 'Hipotireoidismo controlado.', 'Nega comorbidades.', 'Asma leve na infância.'][$indice % 4],
            'cirurgias_previas' => ['Cesárea (2018).', 'Nega.', 'Colecistectomia videolaparoscópica (2020).', 'Nega.'][$indice % 4],
            'alergias' => ['Nega', 'Dipirona', 'Nega', 'Látex'][$indice % 4],
            'medicacoes_em_uso' => ['Nega', 'Anticoncepcional oral', 'Levotiroxina 50 mcg', 'Nega'][$indice % 4],
            'tabagismo' => ['nao', 'nao', 'ex', 'nao'][$indice % 4],
            'etilismo' => ['social', 'nao', 'social', 'nao'][$indice % 4],
            'peso' => $peso,
            'altura' => $altura,
            'imc' => round($peso / ($altura ** 2), 1),
            'expectativas' => $cirurgico
                ? 'Resultado natural e retorno ao trabalho em até 15 dias.'
                : 'Melhora gradual, sem aspecto artificial.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prescricao(bool $cirurgico): array
    {
        if ($cirurgico) {
            return [
                'dados' => ['itens' => [
                    ['medicamento' => 'Dipirona', 'dose' => '1 g', 'posologia' => '1 comprimido de 6/6h se dor', 'duracao' => '5 dias'],
                    ['medicamento' => 'Cefalexina', 'dose' => '500 mg', 'posologia' => '1 cápsula de 6/6h', 'duracao' => '7 dias'],
                    ['medicamento' => 'Ondansetrona', 'dose' => '8 mg', 'posologia' => '1 comprimido de 8/8h se náusea', 'duracao' => '3 dias'],
                ]],
                'conteudo' => 'Malha compressiva 24h por 30 dias. Evitar esforço físico e exposição solar nas cicatrizes.',
            ];
        }

        return [
            'dados' => ['itens' => [
                ['medicamento' => 'Ácido azelaico 15% gel', 'dose' => 'camada fina', 'posologia' => 'aplicar à noite', 'duracao' => '60 dias'],
                ['medicamento' => 'Protetor solar FPS 50 com cor', 'dose' => '—', 'posologia' => 'reaplicar a cada 3 horas', 'duracao' => 'uso contínuo'],
                ['medicamento' => 'Hidratante com ceramidas', 'dose' => '—', 'posologia' => 'manhã e noite', 'duracao' => 'uso contínuo'],
            ]],
            'conteudo' => 'Evitar sol entre 10h e 16h. Não usar ácidos na semana do procedimento.',
        ];
    }

    private function evolucao(bool $cirurgico, int $n): string
    {
        $textos = $cirurgico
            ? [
                '<p>Retorno de 7 dias. Ferida operatória limpa, sem sinais flogísticos. Retirada de pontos. Orientada a manter a malha compressiva.</p>',
                '<p>30 dias de pós-operatório. Edema em regressão, cicatriz em maturação. <strong>Liberada atividade física leve.</strong></p>',
                '<p>90 dias. Paciente satisfeita com o resultado. Fotos de controle realizadas.</p>',
            ]
            : [
                '<p>Procedimento realizado sem intercorrências. Orientados cuidados pós-procedimento e fotoproteção.</p>',
                '<p>Retorno de 15 dias: boa resposta, retoque pontual em região glabelar.</p>',
                '<p>Melhora importante das manchas. <strong>Mantido protocolo domiciliar.</strong></p>',
            ];

        return $textos[$n % count($textos)];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function fotos(int $indice, bool $comDepois): array
    {
        $fotos = [];
        $arquivo = 2000 + $indice * 10;

        foreach (['frontal', 'perfil_direito', 'obliqua_esquerda'] as $angulo) {
            $fotos[] = [
                'momento' => 'antes',
                'angulo' => $angulo,
                'arquivo' => 'IMG_' . $arquivo++ . '.jpg',
                'legenda' => 'Antes — ' . MedicalRecord::ANGULOS[$angulo],
            ];
        }

        if ($comDepois) {
            foreach (['frontal', 'perfil_direito'] as $angulo) {
                $fotos[] = [
                    'momento' => 'depois',
                    'angulo' => $angulo,
                    'arquivo' => 'IMG_' . $arquivo++ . '.jpg',
                    'legenda' => 'Depois — ' . MedicalRecord::ANGULOS[$angulo],
                ];
            }
        }

        return $fotos;
    }
}
