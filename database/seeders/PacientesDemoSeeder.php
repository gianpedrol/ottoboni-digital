<?php

namespace Database\Seeders;

use App\Enums\KommoSyncStatus;
use App\Enums\OrigemPaciente;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\Pacientes\SincronizadorKommo;
use App\Support\CatalogoClinica;
use App\Support\Cpf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 40 pacientes fictícios, metade de cada médico.
 * Apaga os pacientes demo = true e recria (os agendamentos e registros de
 * prontuário demo deles vão junto, pela chave estrangeira). Rode pelo
 * DemoSeeder para recriar tudo na ordem certa.
 */
class PacientesDemoSeeder extends Seeder
{
    private const TOTAL = 40;

    private const NOMES_F = [
        'Ana Beatriz', 'Juliana', 'Camila', 'Fernanda', 'Mariana', 'Patrícia', 'Aline', 'Letícia',
        'Gabriela', 'Renata', 'Carolina', 'Larissa', 'Bruna', 'Priscila', 'Tatiane', 'Daniela',
        'Isabela', 'Luciana', 'Natália', 'Simone', 'Débora', 'Raquel', 'Cristiane', 'Beatriz',
        'Amanda', 'Sabrina', 'Helena', 'Marina', 'Viviane', 'Adriana', 'Elaine', 'Paula',
    ];

    private const NOMES_M = ['Rafael', 'Gustavo', 'Thiago', 'Rodrigo', 'Marcelo', 'Felipe', 'André', 'Lucas'];

    private const SOBRENOMES = [
        'Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Costa', 'Rodrigues', 'Almeida',
        'Nascimento', 'Lima', 'Araújo', 'Ferreira', 'Carvalho', 'Gomes', 'Martins', 'Rocha',
        'Ribeiro', 'Barbosa', 'Mendes', 'Cardoso', 'Teixeira', 'Moreira', 'Correia', 'Prado',
        'Vieira', 'Castro', 'Pinto', 'Moura', 'Cavalcanti', 'Dias', 'Batista', 'Freitas',
    ];

    private const ORIGENS = [
        OrigemPaciente::Instagram, OrigemPaciente::OrganicoIa, OrigemPaciente::Indicacao, OrigemPaciente::Instagram,
        OrigemPaciente::Google, OrigemPaciente::TrafegoPago, OrigemPaciente::OrganicoIa, OrigemPaciente::Instagram,
    ];

    private const INDICACOES = [
        'Paciente Juliana Prado', 'Dra. Marta (ginecologista)', 'Paciente Camila Rocha', 'Amiga da paciente Renata Lima',
    ];

    private const OBSERVACOES = [
        'Prefere contato por WhatsApp no fim da tarde.',
        'Tem receio de anestesia geral; quer conversar sobre sedação.',
        'Quer fazer o procedimento antes de dezembro.',
        'Pediu orçamento com parcelamento no cartão.',
        null, null, null,
    ];

    public function run(): void
    {
        mt_srand(20260910);

        $eduardo = Doctor::query()->firstOrCreate(
            ['agente' => 'duda'],
            ['nome' => 'Dr. Eduardo Ottoboni', 'kommo_pipeline_id' => config('kommo.pipelines.duda'), 'ativo' => true],
        );

        $vanessa = Doctor::query()->firstOrCreate(
            ['agente' => 'luna'],
            ['nome' => 'Dra. Vanessa Ottoboni', 'kommo_pipeline_id' => config('kommo.pipelines.luna'), 'ativo' => true],
        );

        Patient::query()->where('demo', true)->delete();

        $sincronizador = app(SincronizadorKommo::class);

        for ($i = 0; $i < self::TOTAL; $i++) {
            $doctor = $i % 2 === 0 ? $eduardo : $vanessa;
            $feminino = $i % 5 !== 4;

            $primeiro = $feminino
                ? self::NOMES_F[$i % count(self::NOMES_F)]
                : self::NOMES_M[intdiv($i, 5) % count(self::NOMES_M)];
            $sobrenome = self::SOBRENOMES[($i * 7) % count(self::SOBRENOMES)];
            $segundo = $i % 3 === 0 ? ' ' . self::SOBRENOMES[($i * 11 + 5) % count(self::SOBRENOMES)] : '';
            $nome = "{$primeiro} {$sobrenome}{$segundo}";

            $procedimentos = CatalogoClinica::procedimentos($doctor);
            $origem = self::ORIGENS[$i % count(self::ORIGENS)];
            $criadoEm = Carbon::now()->subDays(mt_rand(3, 180))->setTime(mt_rand(8, 19), mt_rand(0, 59));

            $patient = new Patient([
                'nome' => $nome,
                'telefone' => sprintf('+55 41 9%04d-%04d', 8100 + $i * 13, mt_rand(1000, 9999)),
                'email' => $i % 7 === 6 ? null : Str::slug(Str::ascii("{$primeiro} {$sobrenome}"), '.') . '@exemplo.com.br',
                'cpf' => $i % 6 === 5 ? null : Cpf::gerar($i + 1),
                'data_nascimento' => Carbon::now()->subYears(mt_rand(22, 58))->subDays(mt_rand(0, 364))->toDateString(),
                'sexo' => $feminino ? 'F' : 'M',
                'doctor_id' => $doctor->id,
                'procedimento_interesse' => $procedimentos[mt_rand(0, count($procedimentos) - 1)],
                'origem' => $origem->value,
                'indicacao' => $origem === OrigemPaciente::Indicacao ? self::INDICACOES[$i % count(self::INDICACOES)] : null,
                'observacoes' => self::OBSERVACOES[$i % count(self::OBSERVACOES)],
                'kommo_sync_status' => KommoSyncStatus::Pendente,
                'demo' => true,
            ]);
            $patient->created_at = $criadoEm;
            $patient->updated_at = $criadoEm;
            $patient->save();

            $this->sincronizacaoFicticia($patient, $sincronizador, $i, $criadoEm);
        }
    }

    /**
     * ~60% sincronizado (ids fictícios), ~30% simulado, ~10% pendente.
     */
    private function sincronizacaoFicticia(Patient $patient, SincronizadorKommo $sincronizador, int $i, Carbon $criadoEm): void
    {
        $faixa = $i % 10;

        if ($faixa === 9) {
            return;
        }

        $abrirAtendimento = $faixa <= 5 || $i % 2 === 0;
        $payload = $sincronizador->montarPayload($patient, $abrirAtendimento);
        $payload['gerado_em'] = $criadoEm->toIso8601String();

        if ($faixa <= 5) {
            $payload['modo'] = 'enviado';

            $patient->forceFill([
                'kommo_sync_status' => KommoSyncStatus::Sincronizado,
                'kommo_sync_payload' => $payload,
                'kommo_contact_id' => 31_400_000 + $i * 17,
                'kommo_lead_id' => 42_700_000 + $i * 23,
                'kommo_synced_at' => $criadoEm->copy()->addMinute(),
            ])->save();

            return;
        }

        $payload['modo'] = 'simulado';

        $patient->forceFill([
            'kommo_sync_status' => KommoSyncStatus::Simulado,
            'kommo_sync_payload' => $payload,
        ])->save();
    }
}
