<?php

namespace Database\Seeders;

use App\Enums\ModoAutomacao;
use App\Enums\StatusExecucaoAutomacao;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Services\Automacoes\AcaoSimulada;
use App\Services\Automacoes\EventoSimulado;
use App\Services\Automacoes\LeadsDeExemplo;
use App\Services\Automacoes\MotorDeAutomacoes;
use App\Services\Automacoes\ResultadoAvaliacao;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Catálogo inicial A1–A7 (docs/escopo-fase-3.md, seção 5) e ~200 execuções
 * fictícias nos últimos 30 dias. As ações de cada execução saem do próprio
 * motor, então os payloads são os mesmos que o simulador mostra.
 *
 * php artisan db:seed --class=AutomacoesDemoSeeder
 */
class AutomacoesDemoSeeder extends Seeder
{
    private const FALHA_429 = 'Kommo respondeu 429 — nova tentativa agendada';

    /**
     * Quantidade de execuções e leads-modelo (LeadsDeExemplo) por regra.
     *
     * @var array<string, array{0: int, 1: array<int, string>}>
     */
    private const VOLUME = [
        'A1' => [70, ['eduardo_atendimento']],
        'A2' => [52, ['eduardo_fup1', 'vanessa_fup2']],
        'A3' => [28, ['eduardo_agendado']],
        'A4' => [18, ['vanessa_realizou']],
        'A5' => [14, ['eduardo_agendado']],
        'A6' => [6, ['eduardo_negociacao']],
        'A7' => [12, ['eduardo_sinal']],
    ];

    /**
     * Posições (por regra) que viram falha de rate limit — só nas regras ativas.
     *
     * @var array<string, array<int, int>>
     */
    private const FALHAS = ['A1' => [18, 51], 'A2' => [23]];

    /** @var array<int, string> */
    private const MOTIVOS_IGNORADO = [
        'Lead já estava na etapa de destino.',
        'Evento gerado pela própria integração — ignorado (anti-loop).',
        'Condição não atendida: lead fora das etapas previstas.',
    ];

    /** @var array<int, string> */
    private const INSTAGRAM = [
        'ana.lu.ribeiro', 'camila.dt', 'bruna_sfc', 'lari.moraes', 'thais.fit', 'gabi.oliveira',
        'renata.sorocaba', 'jess.m', 'carol_bastos', 'nanda.ferraz', 'priscila.ok', 'mari.tavares',
        'lu.campos', 'bea.nunes', 'vivi.araujo', 'isa.prado', 'manu_rocha', 'duda.lima', 'fer.castro', 'paty.souza',
    ];

    /** @var array<int, string> */
    private const PESSOAS = [
        'Aline Moreira', 'Beatriz Campos', 'Camila Duarte', 'Daniela Ribeiro', 'Elaine Faria',
        'Fabiana Torres', 'Gisele Martins', 'Helena Queiroz', 'Ingrid Sales', 'Joana Freitas',
        'Karina Lopes', 'Luciana Pires', 'Marta Gonçalves', 'Natália Borges', 'Paula Rezende',
        'Rosana Teixeira', 'Sandra Vieira', 'Tatiane Cunha', 'Viviane Brito', 'Priscila Antunes',
    ];

    /**
     * As 7 regras do catálogo, no formato do MotorDeAutomacoes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function regras(): array
    {
        $duda = (int) config('kommo.pipelines.duda');
        $luna = (int) config('kommo.pipelines.luna');
        $tagsFup = ['Contato1', 'Contato2', 'Contato3'];

        return [
            'A1' => [
                'codigo' => 'A1',
                'nome' => 'Tags do FUP',
                'descricao' => 'Entrou em FUP 1, 2 ou 3: põe Contato1/2/3, tira as outras tags de FUP e inicia a régua de follow-up da etapa.',
                'gatilho' => ['tipo' => 'etapa', 'etapas' => ['fup_1', 'fup_2', 'fup_3']],
                'condicoes' => [],
                'acoes' => [
                    ['tipo' => 'adicionar_tag', 'tag' => 'Contato{fup}'],
                    ['tipo' => 'remover_tags', 'tags' => $tagsFup],
                    ['tipo' => 'iniciar_regua'],
                ],
                'pipeline_ids' => [$duda, $luna],
                'modo' => ModoAutomacao::Ativo->value,
                'ordem' => 1,
            ],
            'A2' => [
                'codigo' => 'A2',
                'nome' => 'Agendou no FUP',
                'descricao' => 'Lead em FUP 1–3 ou "Não agendou" com Próxima consulta preenchida: move para "Agendou consulta", tira as tags de FUP e cancela os follow-ups pendentes.',
                'gatilho' => ['tipo' => 'campo_preenchido', 'campo' => 'proxima_consulta'],
                'condicoes' => [
                    ['tipo' => 'etapa_atual', 'etapas' => ['fup_1', 'fup_2', 'fup_3', 'nao_agendou']],
                ],
                'acoes' => [
                    ['tipo' => 'mover_etapa', 'etapa' => 'consulta_agendada'],
                    ['tipo' => 'remover_tags', 'tags' => $tagsFup],
                    ['tipo' => 'cancelar_reguas'],
                ],
                'pipeline_ids' => [$duda, $luna],
                'modo' => ModoAutomacao::Ativo->value,
                'ordem' => 2,
            ],
            'A3' => [
                'codigo' => 'A3',
                'nome' => 'Consulta realizada',
                'descricao' => 'Comparecimento = compareceu: move para "Realizou consulta".',
                'gatilho' => ['tipo' => 'campo_alterado', 'campo' => 'comparecimento', 'valor' => 'Compareceu'],
                'condicoes' => [],
                'acoes' => [
                    ['tipo' => 'mover_etapa', 'etapa' => 'consulta_realizada'],
                ],
                'pipeline_ids' => [$duda, $luna],
                'modo' => ModoAutomacao::SoRegistrar->value,
                'ordem' => 3,
            ],
            'A4' => [
                'codigo' => 'A4',
                'nome' => 'Orçamento enviado',
                'descricao' => 'Lead em "Realizou consulta" com Valor da proposta preenchido: move para "Orçamento / negociação".',
                'gatilho' => ['tipo' => 'campo_preenchido', 'campo' => 'valor_proposta'],
                'condicoes' => [
                    ['tipo' => 'etapa_atual', 'etapas' => ['consulta_realizada']],
                ],
                'acoes' => [
                    ['tipo' => 'mover_etapa', 'etapa' => 'negociacao'],
                ],
                'pipeline_ids' => [$duda, $luna],
                'modo' => ModoAutomacao::SoRegistrar->value,
                'ordem' => 4,
            ],
            'A5' => [
                'codigo' => 'A5',
                'nome' => 'No-show',
                'descricao' => 'Comparecimento = faltou: move para "Não agendou", põe RESGATECONSULTA e inicia a régua de resgate.',
                'gatilho' => ['tipo' => 'campo_alterado', 'campo' => 'comparecimento', 'valor' => 'Faltou'],
                'condicoes' => [],
                'acoes' => [
                    ['tipo' => 'mover_etapa', 'etapa' => 'nao_agendou'],
                    ['tipo' => 'adicionar_tag', 'tag' => 'RESGATECONSULTA'],
                    ['tipo' => 'iniciar_regua'],
                ],
                'pipeline_ids' => [$duda, $luna],
                'modo' => ModoAutomacao::SoRegistrar->value,
                'ordem' => 5,
            ],
            'A6' => [
                'codigo' => 'A6',
                'nome' => 'Contrato assinado',
                'descricao' => 'Data da assinatura preenchida (ou tag CONTRATO): move para "Contratado", cria o card no Fluxo cirurgias com o mesmo contato e os dados da cirurgia, cria a tarefa "Iniciar pré-operatório" e gera o recebível.',
                'gatilho' => [
                    'tipo' => 'campo_preenchido',
                    'campo' => 'data_assinatura',
                    'ou' => [['tipo' => 'tag_adicionada', 'tag' => 'CONTRATO']],
                ],
                'condicoes' => [],
                'acoes' => [
                    ['tipo' => 'mover_etapa', 'etapa' => 'contratado'],
                    [
                        'tipo' => 'criar_card_pipeline',
                        'pipeline' => 'cirurgias',
                        'copiar_campos' => ['cirurgia', 'data_cirurgia', 'hospital', 'anestesista', 'protese', 'valor_total'],
                    ],
                    ['tipo' => 'criar_tarefa', 'texto' => 'Iniciar pré-operatório', 'prazo_horas' => 24],
                    ['tipo' => 'criar_recebivel'],
                ],
                'pipeline_ids' => [$duda, $luna],
                'modo' => ModoAutomacao::SoRegistrar->value,
                'ordem' => 6,
            ],
            'A7' => [
                'codigo' => 'A7',
                'nome' => 'Sinal pago',
                'descricao' => 'Dr Eduardo: Status do sinal = pago tira o lead de "Aguardando sinal", leva para "Agendou consulta" e põe PAGAMENTOOK.',
                'gatilho' => ['tipo' => 'campo_alterado', 'campo' => 'status_sinal', 'valor' => 'Pago'],
                'condicoes' => [
                    ['tipo' => 'etapa_atual', 'etapas' => ['aguardando_sinal']],
                ],
                'acoes' => [
                    ['tipo' => 'mover_etapa', 'etapa' => 'consulta_agendada'],
                    ['tipo' => 'adicionar_tag', 'tag' => 'PAGAMENTOOK'],
                ],
                'pipeline_ids' => [$duda],
                'modo' => ModoAutomacao::SoRegistrar->value,
                'ordem' => 7,
            ],
        ];
    }

    public function run(): void
    {
        AutomationRun::query()->where('demo', true)->delete();

        $regras = [];

        foreach (self::regras() as $codigo => $dados) {
            $regras[$codigo] = AutomationRule::query()->updateOrCreate(
                ['codigo' => $codigo],
                [...$dados, 'demo' => true],
            );
        }

        $this->execucoes($regras);
    }

    /**
     * @param  array<string, AutomationRule>  $regras
     */
    private function execucoes(array $regras): void
    {
        mt_srand(20260910);

        $motor = app(MotorDeAutomacoes::class);
        $agora = CarbonImmutable::now((string) config('painel.timezone'));
        $duda = (int) config('kommo.pipelines.duda');

        foreach (self::VOLUME as $codigo => [$quantidade, $modelos]) {
            $regra = $regras[$codigo];
            $pipelines = array_map(intval(...), $regra->pipeline_ids);

            for ($i = 0; $i < $quantidade; $i++) {
                $pipelineId = $pipelines[mt_rand(0, count($pipelines) - 1)];
                $modelo = $modelos[mt_rand(0, count($modelos) - 1)];
                $leadId = ($pipelineId === $duda ? 23700000 : 31800000) + mt_rand(1000, 399999);

                $lead = LeadsDeExemplo::lead($modelo, $leadId, $this->nomeDeLead(), $pipelineId);
                $resultado = $motor->avaliar($regra, $lead, $this->evento($codigo, $regra), LeadsDeExemplo::camposExtras($modelo));

                [$status, $motivo, $acoes] = $this->desfecho($codigo, $i, $regra, $resultado);

                $quando = $agora->subDays(mt_rand(0, 29))->setTime(mt_rand(8, 19), mt_rand(0, 59));

                if ($quando->greaterThan($agora)) {
                    $quando = $quando->subDay();
                }

                $run = new AutomationRun([
                    'automation_rule_id' => $regra->id,
                    'kommo_lead_id' => $lead->id,
                    'lead_nome' => $lead->name,
                    'pipeline_id' => $lead->pipelineId,
                    'evento' => $resultado->toArray()['evento'],
                    'acoes' => $acoes,
                    'status' => $status,
                    'motivo' => $motivo,
                    'demo' => true,
                ]);

                $run->created_at = $quando->utc();
                $run->updated_at = $quando->utc();
                $run->save();
            }
        }
    }

    private function evento(string $codigo, AutomationRule $regra): ?EventoSimulado
    {
        if ($codigo === 'A1') {
            return EventoSimulado::entrouNaEtapa(['fup_1', 'fup_2', 'fup_3'][mt_rand(0, 2)]);
        }

        if ($codigo === 'A6' && mt_rand(0, 1) === 1) {
            return EventoSimulado::tagAdicionada('CONTRATO');
        }

        return EventoSimulado::sugeridoPara($regra->gatilho);
    }

    /**
     * @return array{0: StatusExecucaoAutomacao, 1: ?string, 2: array<int, array<string, mixed>>}
     */
    private function desfecho(string $codigo, int $i, AutomationRule $regra, ResultadoAvaliacao $resultado): array
    {
        $acoes = array_map(fn (AcaoSimulada $a): array => $a->toArray(), $resultado->acoes);

        if (! $resultado->casou()) {
            return [StatusExecucaoAutomacao::Ignorado, $resultado->motivo(), []];
        }

        if ($i % 12 === 5) {
            return [StatusExecucaoAutomacao::Ignorado, self::MOTIVOS_IGNORADO[intdiv($i, 12) % count(self::MOTIVOS_IGNORADO)], []];
        }

        if (in_array($i, self::FALHAS[$codigo] ?? [], true)) {
            return [StatusExecucaoAutomacao::Falhou, self::FALHA_429, $acoes];
        }

        return $regra->modo === ModoAutomacao::Ativo
            ? [StatusExecucaoAutomacao::Executado, null, $acoes]
            : [StatusExecucaoAutomacao::Registrado, 'Modo só registrar: nada foi enviado ao Kommo.', $acoes];
    }

    private function nomeDeLead(): string
    {
        return mt_rand(0, 1) === 1
            ? 'IG @' . self::INSTAGRAM[mt_rand(0, count(self::INSTAGRAM) - 1)]
            : self::PESSOAS[mt_rand(0, count(self::PESSOAS) - 1)];
    }
}
