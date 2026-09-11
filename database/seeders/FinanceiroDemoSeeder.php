<?php

namespace Database\Seeders;

use App\Enums\CategoriaDespesa;
use App\Enums\FormaPagamento;
use App\Enums\OrigemRecebivel;
use App\Enums\StatusFinanceiro;
use App\Enums\TipoServico;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Service;
use App\Support\Financeiro;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Financeiro fictício do protótipo: tabela de preços, recebíveis dos
 * últimos 6 meses e dos próximos 3, contas a pagar recorrentes e extrato
 * de 3 meses, quase todo conciliado.
 *
 * Idempotente: apaga só as linhas demo = true deste módulo e recria. A
 * semente do faker é fixa, então cada rodada gera os mesmos números
 * (relativos à data de hoje).
 */
class FinanceiroDemoSeeder extends Seeder
{
    private Generator $faker;

    private CarbonImmutable $hoje;

    /** @var Collection<int, Patient> */
    private Collection $pacientes;

    /** @var array<int, CarbonImmutable> datas de cirurgia, para hospital e anestesista */
    private array $cirurgias = [];

    private int $sequenciaFitid = 0;

    public function run(): void
    {
        $this->faker = FakerFactory::create('pt_BR');
        $this->faker->seed(20260910);
        $this->hoje = Financeiro::hoje();
        $this->cirurgias = [];
        $this->sequenciaFitid = 0;

        DB::transaction(function (): void {
            $this->limpar();

            $eduardo = $this->medico('duda', 'Dr. Eduardo Ottoboni');
            $vanessa = $this->medico('luna', 'Dra. Vanessa Ottoboni');

            $servicos = $this->servicos($eduardo, $vanessa);
            $this->pacientes = Patient::query()->where('demo', true)->get();

            $this->recebiveis($eduardo, $vanessa, $servicos);
            $this->contasAPagar();
            $this->pendenciasDoExtratoDeExemplo($eduardo, $vanessa, $servicos);
            $this->extrato();
        });
    }

    private function limpar(): void
    {
        BankTransaction::query()->where('demo', true)->delete();
        BankAccount::query()->where('demo', true)->delete();
        ReceivableInstallment::query()->where('demo', true)->delete();
        Receivable::query()->where('demo', true)->delete();
        Payable::query()->where('demo', true)->delete();
        Service::query()->where('demo', true)->delete();
    }

    private function medico(string $agente, string $nome): Doctor
    {
        return Doctor::query()->firstOrCreate(
            ['agente' => $agente],
            [
                'nome' => $nome,
                'kommo_pipeline_id' => config("kommo.pipelines.{$agente}"),
                'ativo' => true,
            ],
        );
    }

    /**
     * @return Collection<string, Service> por nome
     */
    private function servicos(Doctor $eduardo, Doctor $vanessa): Collection
    {
        // [nome, tipo, valor, duração em min, % repasse]
        $tabela = [
            $eduardo->id => [
                ['Consulta de cirurgia plástica', TipoServico::Consulta, 600, 60, 70],
                ['Retorno de consulta', TipoServico::Retorno, 300, 30, 70],
                ['Mamoplastia de aumento', TipoServico::Cirurgia, 18000, 180, 35],
                ['Abdominoplastia', TipoServico::Cirurgia, 22000, 240, 35],
                ['Lipoaspiração', TipoServico::Cirurgia, 16500, 180, 35],
                ['Blefaroplastia', TipoServico::Cirurgia, 9500, 120, 40],
                ['Rinoplastia', TipoServico::Cirurgia, 15000, 180, 35],
                ['Mastopexia com prótese', TipoServico::Cirurgia, 24000, 240, 35],
            ],
            $vanessa->id => [
                ['Consulta dermatológica', TipoServico::Consulta, 450, 40, 70],
                ['Retorno dermatológico', TipoServico::Retorno, 250, 20, 70],
                ['Toxina botulínica (3 regiões)', TipoServico::Procedimento, 1800, 30, 45],
                ['Preenchimento com ácido hialurônico', TipoServico::Procedimento, 2200, 45, 45],
                ['Bioestimulador de colágeno', TipoServico::Procedimento, 2800, 45, 45],
                ['Ultraformer III (face e pescoço)', TipoServico::Procedimento, 3900, 60, 40],
                ['Laser CO2 fracionado', TipoServico::Procedimento, 2500, 60, 40],
                ['Peeling químico', TipoServico::Procedimento, 800, 40, 50],
            ],
        ];

        $servicos = collect();

        foreach ($tabela as $doctorId => $linhas) {
            foreach ($linhas as [$nome, $tipo, $valor, $duracao, $repasse]) {
                $servicos[$nome] = Service::query()->create([
                    'doctor_id' => $doctorId,
                    'nome' => $nome,
                    'tipo' => $tipo->value,
                    'valor' => $valor,
                    'duracao_min' => $duracao,
                    'repasse_pct' => $repasse,
                    'ativo' => true,
                    'demo' => true,
                ]);
            }
        }

        return $servicos;
    }

    /**
     * @param  Collection<string, Service>  $servicos
     */
    private function recebiveis(Doctor $eduardo, Doctor $vanessa, Collection $servicos): void
    {
        $cirurgias = $servicos->filter(fn (Service $s): bool => $s->tipo === TipoServico::Cirurgia->value)->values();
        $procedimentos = $servicos->filter(fn (Service $s): bool => $s->tipo === TipoServico::Procedimento->value)->values();

        for ($m = -6; $m <= 3; $m++) {
            $mes = $this->hoje->startOfMonth()->addMonthsNoOverflow($m);
            $futuro = $m > 0;

            // Dr. Eduardo: consultas, retornos e contratos de cirurgia.
            foreach (range(1, $this->faker->numberBetween($futuro ? 5 : 10, $futuro ? 8 : 14)) as $_) {
                $this->avulso($eduardo, $servicos['Consulta de cirurgia plástica'], $this->diaUtil($mes), OrigemRecebivel::Consulta);
            }

            foreach (range(1, $this->faker->numberBetween(3, 5)) as $_) {
                $this->avulso($eduardo, $servicos['Retorno de consulta'], $this->diaUtil($mes), OrigemRecebivel::Consulta);
            }

            // Contrato só é assinado até o mês atual; os futuros vêm das parcelas.
            if (! $futuro) {
                foreach (range(1, $this->faker->numberBetween(3, 5)) as $_) {
                    $this->contrato($eduardo, $this->faker->randomElement($cirurgias->all()), $this->diaUtil($mes));
                }
            }

            // Dra. Vanessa: consultas, retornos e procedimentos.
            foreach (range(1, $this->faker->numberBetween($futuro ? 7 : 14, $futuro ? 10 : 18)) as $_) {
                $this->avulso($vanessa, $servicos['Consulta dermatológica'], $this->diaUtil($mes), OrigemRecebivel::Consulta);
            }

            foreach (range(1, $this->faker->numberBetween(4, 6)) as $_) {
                $this->avulso($vanessa, $servicos['Retorno dermatológico'], $this->diaUtil($mes), OrigemRecebivel::Consulta);
            }

            foreach (range(1, $this->faker->numberBetween($futuro ? 6 : 18, $futuro ? 9 : 24)) as $_) {
                $this->avulso($vanessa, $this->faker->randomElement($procedimentos->all()), $this->diaUtil($mes), OrigemRecebivel::Procedimento);
            }
        }
    }

    /**
     * Consulta, retorno ou procedimento: à vista (PIX, dinheiro) ou no
     * cartão, com procedimento podendo ir em até 3x.
     */
    private function avulso(Doctor $doctor, Service $servico, CarbonImmutable $data, OrigemRecebivel $origem): void
    {
        $forma = $this->faker->randomElement([
            FormaPagamento::Pix, FormaPagamento::Pix, FormaPagamento::Pix,
            FormaPagamento::Cartao, FormaPagamento::Cartao, FormaPagamento::Cartao,
            FormaPagamento::Dinheiro,
        ]);

        $parcelas = $origem === OrigemRecebivel::Procedimento && $forma === FormaPagamento::Cartao
            ? $this->faker->numberBetween(1, 3)
            : 1;

        $this->criarRecebivel($doctor, $servico, $data, $parcelas, $forma, $origem, $servico->nome);
    }

    private function contrato(Doctor $doctor, Service $servico, CarbonImmutable $data): void
    {
        $forma = $this->faker->boolean(60) ? FormaPagamento::Boleto : FormaPagamento::Cartao;
        $parcelas = $this->faker->randomElement([3, 4, 5, 6, 8, 10]);

        $this->criarRecebivel($doctor, $servico, $data, $parcelas, $forma, OrigemRecebivel::Contrato, 'Contrato · ' . $servico->nome);

        // A cirurgia acontece algumas semanas depois da assinatura.
        $this->cirurgias[] = $data->addDays($this->faker->numberBetween(20, 35));
    }

    private function criarRecebivel(
        Doctor $doctor,
        Service $servico,
        CarbonImmutable $primeiroVencimento,
        int $parcelas,
        FormaPagamento $forma,
        OrigemRecebivel $origem,
        string $descricao,
        ?string $nomeSemCadastro = null,
        bool $sempreEmAberto = false,
    ): Receivable {
        $paciente = $nomeSemCadastro === null ? $this->paciente($doctor) : null;

        // Sem pacientes de demonstração, o nome vai na descrição para a lista não ficar anônima.
        if ($paciente === null) {
            $descricao .= ' · ' . ($nomeSemCadastro ?? $this->faker->firstName() . ' ' . $this->faker->lastName());
        }

        $receivable = Receivable::query()->create([
            'patient_id' => $paciente?->id,
            'doctor_id' => $doctor->id,
            'service_id' => $servico->id,
            'descricao' => $descricao,
            'valor_total' => $servico->valor,
            'forma_pagamento' => $forma->value,
            'origem' => $origem->value,
            'demo' => true,
        ]);

        // Nasce na data do atendimento/assinatura: o dashboard conta novos contratos por ela
        $criadoEm = $primeiroVencimento->isFuture() ? $this->hoje : $primeiroVencimento;
        $receivable->forceFill(['created_at' => $criadoEm, 'updated_at' => $criadoEm])->saveQuietly();

        $agora = now();
        $linhas = [];

        foreach (Financeiro::dividir((float) $servico->valor, $parcelas) as $i => $valor) {
            $vencimento = $primeiroVencimento->addMonthsNoOverflow($i);
            [$status, $pagoEm] = $sempreEmAberto ? [StatusFinanceiro::Aberto, null] : $this->situacaoDaParcela($vencimento, $forma);

            $linhas[] = [
                'receivable_id' => $receivable->id,
                'numero' => $i + 1,
                'valor' => $valor,
                'vencimento' => $vencimento->toDateString(),
                'pago_em' => $pagoEm?->toDateString(),
                'valor_pago' => $pagoEm !== null ? $valor : null,
                'status' => $status->value,
                'demo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        ReceivableInstallment::query()->insert($linhas);

        return $receivable;
    }

    /**
     * Parcela futura fica em aberto; vencida é paga, exceto ~12% das que
     * não são de cartão (cartão não atrasa: a maquininha já aprovou).
     *
     * @return array{0: StatusFinanceiro, 1: CarbonImmutable|null}
     */
    private function situacaoDaParcela(CarbonImmutable $vencimento, FormaPagamento $forma): array
    {
        if ($vencimento->gt($this->hoje)) {
            return [StatusFinanceiro::Aberto, null];
        }

        if ($forma !== FormaPagamento::Cartao && $this->faker->boolean(12)) {
            return [StatusFinanceiro::Aberto, null];
        }

        $pagoEm = $forma === FormaPagamento::Boleto
            ? $vencimento->addDays($this->faker->numberBetween(0, 3))
            : $vencimento;

        return [StatusFinanceiro::Pago, $pagoEm->min($this->hoje)];
    }

    private function contasAPagar(): void
    {
        // [fornecedor, categoria, descrição, valor base, dia do vencimento, variação %]
        $recorrentes = [
            ['Imobiliária Campolim', CategoriaDespesa::Aluguel, 'Aluguel e condomínio da clínica', 12000, 5, 0],
            ['Folha de pagamento', CategoriaDespesa::Folha, 'Salários da equipe (recepção, enfermagem, limpeza)', 26000, 5, 3],
            ['INSS e FGTS', CategoriaDespesa::Impostos, 'Encargos da folha', 7800, 20, 3],
            ['Simples Nacional', CategoriaDespesa::Impostos, 'DAS do mês', 9000, 20, 12],
            ['Meta Ads', CategoriaDespesa::Marketing, 'Campanhas no Instagram e Facebook', 6000, 10, 15],
            ['Kommo', CategoriaDespesa::Software, 'CRM e atendimento (assinatura mensal)', 1290, 12, 0],
            ['Prontuário eletrônico', CategoriaDespesa::Software, 'Sistema de prontuário e agenda', 690, 12, 0],
            ['Contabilidade Ribeiro & Associados', CategoriaDespesa::Contador, 'Honorários contábeis', 1800, 10, 0],
            ['CPFL Energia', CategoriaDespesa::Energia, 'Conta de energia', 2600, 20, 12],
            ['Cirúrgica Paulista Materiais', CategoriaDespesa::Materiais, 'Materiais e descartáveis cirúrgicos', 5500, 15, 25],
        ];

        for ($m = -6; $m <= 3; $m++) {
            $mes = $this->hoje->startOfMonth()->addMonthsNoOverflow($m);

            foreach ($recorrentes as [$fornecedor, $categoria, $descricao, $valor, $dia, $variacao]) {
                $fator = 1 + $this->faker->numberBetween(-$variacao, $variacao) / 100;

                $this->criarConta($fornecedor, $categoria, $descricao, round($valor * $fator, 2), $mes->setDay($dia), true);
            }
        }

        foreach ($this->cirurgias as $data) {
            $this->criarConta(
                'Hospital Unimed Sorocaba',
                CategoriaDespesa::Hospital,
                'Taxa de sala e internação · cirurgia de ' . $data->format('d/m'),
                $this->faker->numberBetween(45, 70) * 100,
                $data,
                false,
            );

            $this->criarConta(
                $this->faker->randomElement(['Dr. Ricardo Lima', 'Dra. Paula Menezes']),
                CategoriaDespesa::Anestesista,
                'Honorários de anestesia · cirurgia de ' . $data->format('d/m'),
                $this->faker->randomElement([2500, 2800, 3200, 3500]),
                $data->addDays(5),
                false,
            );
        }
    }

    private function criarConta(
        string $fornecedor,
        CategoriaDespesa $categoria,
        string $descricao,
        float $valor,
        CarbonImmutable $vencimento,
        bool $recorrente,
        bool $sempreEmAberto = false,
    ): Payable {
        $pagoEm = null;

        // Conta vencida está paga, salvo alguma esquecida no último mês e meio.
        if (! $sempreEmAberto && $vencimento->lte($this->hoje)) {
            $esquecida = $vencimento->gte($this->hoje->subDays(45)) && $this->faker->boolean(8);
            $pagoEm = $esquecida ? null : $vencimento->subDays($this->faker->numberBetween(0, 2));
        }

        return Payable::query()->create([
            'fornecedor' => $fornecedor,
            'categoria' => $categoria->value,
            'descricao' => $descricao,
            'valor' => $valor,
            'vencimento' => $vencimento->toDateString(),
            'pago_em' => $pagoEm?->toDateString(),
            'recorrente' => $recorrente,
            'status' => ($pagoEm !== null ? StatusFinanceiro::Pago : StatusFinanceiro::Aberto)->value,
            'demo' => true,
        ]);
    }

    /**
     * Itens em aberto que casam com o storage/app/demo/extrato-exemplo.ofx:
     * na demonstração, importar o arquivo e clicar em "Conciliar" já traz
     * a sugestão certa. Valores e datas iguais aos do arquivo.
     *
     * @param  Collection<string, Service>  $servicos
     */
    private function pendenciasDoExtratoDeExemplo(Doctor $eduardo, Doctor $vanessa, Collection $servicos): void
    {
        $data = fn (string $d): CarbonImmutable => CarbonImmutable::parse($d, config('painel.timezone'));

        $this->criarRecebivel($vanessa, $servicos['Toxina botulínica (3 regiões)'], $data('2026-09-01'), 1, FormaPagamento::Pix, OrigemRecebivel::Procedimento, 'Toxina botulínica (3 regiões)', 'Camila R. Souza', true);
        $this->criarRecebivel($eduardo, $servicos['Consulta de cirurgia plástica'], $data('2026-09-02'), 1, FormaPagamento::Pix, OrigemRecebivel::Consulta, 'Consulta de cirurgia plástica', 'Juliana M. Pereira', true);
        $this->criarRecebivel($vanessa, $servicos['Preenchimento com ácido hialurônico'], $data('2026-09-03'), 1, FormaPagamento::Cartao, OrigemRecebivel::Procedimento, 'Preenchimento com ácido hialurônico', 'Renata C. Dias', true);
        $this->criarRecebivel($vanessa, $servicos['Consulta dermatológica'], $data('2026-09-08'), 1, FormaPagamento::Pix, OrigemRecebivel::Consulta, 'Consulta dermatológica', 'Fernanda L. Alves', true);

        // Contrato de mamoplastia em 5x de R$ 3.600: a 1ª já paga, a 2ª é o boleto do OFX.
        $contrato = $this->criarRecebivel($eduardo, $servicos['Mamoplastia de aumento'], $data('2026-08-04'), 5, FormaPagamento::Boleto, OrigemRecebivel::Contrato, 'Contrato · Mamoplastia de aumento', 'Patrícia G. Lima', true);
        $contrato->installments()->where('numero', 1)->update([
            'pago_em' => '2026-08-05',
            'valor_pago' => 3600,
            'status' => StatusFinanceiro::Pago->value,
        ]);

        $this->criarConta('Medical Supply Sorocaba', CategoriaDespesa::Materiais, 'Fios de sutura, curativos e malhas compressivas', 4870.35, $data('2026-09-03'), false, true);
        $this->criarConta('Dr. Ricardo Lima', CategoriaDespesa::Anestesista, 'Honorários de anestesia · mamoplastia de 04/09', 2500, $data('2026-09-05'), false, true);
    }

    private function extrato(): void
    {
        $inicio = $this->hoje->subMonthsNoOverflow(3)->startOfDay();

        $itau = BankAccount::query()->create([
            'apelido' => 'Conta movimento',
            'banco' => 'Itaú',
            'agencia' => '1582',
            'conta' => '45678-9',
            'saldo_inicial' => 185000,
            'demo' => true,
        ]);

        $inter = BankAccount::query()->create([
            'apelido' => 'Recebimentos PIX e cartão',
            'banco' => 'Banco Inter',
            'agencia' => '0001',
            'conta' => '1234567-8',
            'saldo_inicial' => 38000,
            'demo' => true,
        ]);

        $lancamentos = collect();

        // Entradas: PIX e cartão caem no Inter; boleto e dinheiro, no Itaú.
        $recebidas = ReceivableInstallment::query()
            ->where('demo', true)
            ->pagas()
            ->whereBetween('pago_em', [$inicio->toDateString(), $this->hoje->toDateString()])
            ->with('receivable.patient')
            ->get();

        foreach ($recebidas as $parcela) {
            $receivable = $parcela->receivable;
            $nome = $receivable->patient->nome ?? Str::afterLast($receivable->descricao, ' · ');

            [$conta, $descricao] = match ($receivable->forma_pagamento) {
                FormaPagamento::Pix->value => [$inter, 'PIX RECEBIDO ' . $this->memo($nome)],
                FormaPagamento::Cartao->value => [$inter, $this->faker->randomElement(['CIELO VENDAS CREDITO', 'REDE VENDAS CREDITO', 'CIELO VENDAS DEBITO'])],
                FormaPagamento::Boleto->value => [$itau, 'LIQUIDACAO BOLETO ' . $this->memo($nome)],
                default => [$itau, 'DEPOSITO EM DINHEIRO'],
            };

            $lancamentos->push([$conta, $parcela->pago_em, (float) $parcela->valor_pago, $descricao, $parcela]);
        }

        // Saídas: tudo pela conta movimento.
        $pagas = Payable::query()
            ->where('demo', true)
            ->pagas()
            ->whereBetween('pago_em', [$inicio->toDateString(), $this->hoje->toDateString()])
            ->get();

        foreach ($pagas as $conta) {
            $descricao = match ($conta->categoria) {
                CategoriaDespesa::Folha->value => 'PAGTO SALARIOS FOLHA',
                CategoriaDespesa::Energia->value => 'DEB AUTOMATICO CPFL ENERGIA',
                CategoriaDespesa::Impostos->value => 'PAGTO GUIA ' . $this->memo($conta->fornecedor),
                CategoriaDespesa::Anestesista->value => 'TED ' . $this->memo($conta->fornecedor),
                CategoriaDespesa::Marketing->value => 'FACEBK META ADS',
                default => 'PAG BOLETO ' . $this->memo($conta->fornecedor),
            };

            $lancamentos->push([$itau, $conta->pago_em, -1 * (float) $conta->valor, $descricao, $conta]);
        }

        // Lançamentos só do banco, nos meses fechados (o mês atual vem do OFX de exemplo).
        for ($mes = $inicio->startOfMonth(); $mes->lt($this->hoje->startOfMonth()); $mes = $mes->addMonthNoOverflow()) {
            $transferencia = $this->faker->numberBetween(50, 65) * 1000;

            $lancamentos->push([$itau, $mes->setDay(8), -89.90, 'TARIFA PACOTE SERVICOS', null]);
            $lancamentos->push([$inter, $mes->setDay(25), -1 * $transferencia, 'TRANSF ENVIADA ITAU CONTA MOVIMENTO', null]);
            $lancamentos->push([$itau, $mes->setDay(25), $transferencia, 'TRANSF RECEBIDA BANCO INTER', null]);
            $lancamentos->push([$itau, $mes->endOfMonth()->startOfDay(), $this->faker->numberBetween(15000, 60000) / 100, 'RENDIMENTO APLIC AUTOMATICA', null]);
        }

        // Em ordem de data, para o saldo corrido do extrato fazer sentido.
        $lancamentos
            ->filter(fn (array $l): bool => $l[1]->gte($inicio))
            ->sortBy(fn (array $l): string => $l[1]->format('Y-m-d'))
            ->each(fn (array $l) => $this->lancar(...$l));
    }

    /**
     * Grava o lançamento já conciliado com o item. Nos últimos 20 dias,
     * parte fica pendente e o item volta a "em aberto": é o que a
     * conciliação da demonstração vai baixar.
     */
    private function lancar(BankAccount $conta, CarbonInterface $data, float $valor, string $descricao, ReceivableInstallment|Payable|null $item): void
    {
        $data = CarbonImmutable::parse($data);
        $recente = $data->gte($this->hoje->subDays(20));
        $conciliadoEm = $data->addDay()->setTime(10, 0)->min(now());

        $atributos = [
            'data' => $data->toDateString(),
            'descricao' => $descricao,
            'valor' => $valor,
            'fitid' => 'DEMO' . str_pad((string) ++$this->sequenciaFitid, 6, '0', STR_PAD_LEFT),
            'demo' => true,
        ];

        if ($item === null) {
            $conta->transactions()->create([...$atributos, 'conciliado_em' => $recente ? null : $conciliadoEm]);

            return;
        }

        if ($recente && $this->faker->boolean(30)) {
            $item->update($item instanceof Payable
                ? ['pago_em' => null, 'status' => StatusFinanceiro::Aberto->value]
                : ['pago_em' => null, 'valor_pago' => null, 'status' => StatusFinanceiro::Aberto->value]);

            $conta->transactions()->create($atributos);

            return;
        }

        $conta->transactions()->create([
            ...$atributos,
            'conciliavel_type' => $item->getMorphClass(),
            'conciliavel_id' => $item->getKey(),
            'conciliado_em' => $conciliadoEm,
        ]);
    }

    private function paciente(Doctor $doctor): ?Patient
    {
        if ($this->pacientes->isEmpty()) {
            return null;
        }

        $doMedico = $this->pacientes->where('doctor_id', $doctor->id);

        // randomElement (e não ->random()) para a semente do faker valer aqui também.
        return $this->faker->randomElement(($doMedico->isNotEmpty() ? $doMedico : $this->pacientes)->all());
    }

    /**
     * Dia útil aleatório do mês (sem domingo).
     */
    private function diaUtil(CarbonImmutable $mes): CarbonImmutable
    {
        $dia = $mes->setDay($this->faker->numberBetween(1, $mes->daysInMonth));

        return $dia->isSunday() ? ($dia->day > 1 ? $dia->subDay() : $dia->addDay()) : $dia;
    }

    /**
     * Nome como aparece no extrato: maiúsculo, sem acento, sem "Dr.".
     */
    private function memo(string $nome): string
    {
        return Str::of($nome)->ascii()->upper()->replace(['.', ','], '')->squish()->limit(40, '')->toString();
    }
}
