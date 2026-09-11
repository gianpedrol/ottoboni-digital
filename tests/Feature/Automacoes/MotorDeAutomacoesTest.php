<?php

use App\Models\AutomationRule;
use App\Services\Automacoes\EventoSimulado;
use App\Services\Automacoes\MotorDeAutomacoes;
use App\Services\Automacoes\NormalizadorDeRegra;
use App\Services\Kommo\DTO\LeadData;
use Carbon\CarbonImmutable;
use Database\Seeders\AutomacoesDemoSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    $this->motor = app(MotorDeAutomacoes::class);
});

// Motor é dry-run: nenhuma chamada HTTP, de nenhum tipo.
afterEach(function () {
    Http::assertNothingSent();
});

function regraDoCatalogo(string $codigo): AutomationRule
{
    return AutomationRule::query()->create(AutomacoesDemoSeeder::regras()[$codigo]);
}

/**
 * @param  array<string, mixed>  $extra  argumentos nomeados do LeadData
 */
function leadAutomacao(string $agente, int $statusId, array $extra = []): LeadData
{
    return new LeadData(...[
        'id' => 777001,
        'name' => 'IG @paciente.teste',
        'pipelineId' => (int) config("kommo.pipelines.{$agente}"),
        'statusId' => $statusId,
        'responsibleUserId' => null,
        'createdAt' => CarbonImmutable::now()->subDays(3),
        'updatedAt' => CarbonImmutable::now()->subHour(),
        'closedAt' => null,
        'price' => 0,
        'origem' => null,
        'temperatura' => null,
        'procedimento' => null,
        'urgencia' => null,
        'score' => null,
        'instagram' => null,
        'contactIds' => [555001],
        'lossReason' => null,
        ...$extra,
    ]);
}

// ------------------------------------------------------------------ A1

it('A1 casa: lead entra em FUP 2 no Dr Eduardo, ganha Contato2, perde Contato1 e inicia a régua', function () {
    $lead = leadAutomacao('duda', 84709188, ['tags' => ['Contato1', 'botox']]);

    $r = $this->motor->avaliar(regraDoCatalogo('A1'), $lead, EventoSimulado::entrouNaEtapa('fup_2'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes)->toHaveCount(3);

    [$adicionar, $remover, $regua] = $r->acoes;

    expect($adicionar->descricao)->toBe('Adicionar a tag Contato2')
        ->and($adicionar->metodo)->toBe('PATCH')
        ->and($adicionar->endpoint)->toBe('/api/v4/leads/777001')
        ->and($adicionar->payload)->toBe(['_embedded' => ['tags' => [
            ['name' => 'Contato1'], ['name' => 'botox'], ['name' => 'Contato2'],
        ]]])
        ->and($remover->descricao)->toBe('Remover a tag Contato1')
        ->and($remover->payload)->toBe(['_embedded' => ['tags' => [['name' => 'botox'], ['name' => 'Contato2']]]])
        ->and($regua->tipo)->toBe('iniciar_regua')
        ->and($regua->metodo)->toBe('PAINEL')
        ->and($regua->payload['status_id'])->toBe(84709192);
});

it('A1 casa na Dra Vanessa: FU3 vira Contato3 e não há tag de FUP para tirar', function () {
    $lead = leadAutomacao('luna', 71591208);

    $r = $this->motor->avaliar(regraDoCatalogo('A1'), $lead, EventoSimulado::entrouNaEtapa('fup_3'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->payload)->toBe(['_embedded' => ['tags' => [['name' => 'Contato3']]]])
        ->and($r->acoes[1]->aplicavel)->toBeFalse()
        ->and($r->acoes[2]->payload['status_id'])->toBe(110706667);
});

it('A1 não casa quando o lead entra numa etapa que não é FUP', function () {
    $lead = leadAutomacao('duda', 54917475);

    $r = $this->motor->avaliar(regraDoCatalogo('A1'), $lead, EventoSimulado::entrouNaEtapa('consulta_agendada'));

    expect($r->casou())->toBeFalse()
        ->and($r->gatilhoCasou)->toBeFalse()
        ->and($r->gatilhoMotivo)->toContain('FUP 1, FUP 2 ou FUP 3')
        ->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------------ A2

it('A2 casa no Dr Eduardo: Próxima consulta preenchida no FUP move para 54917479', function () {
    $lead = leadAutomacao('duda', 84709188, ['tags' => ['Contato1']]);

    $r = $this->motor->avaliar(regraDoCatalogo('A2'), $lead, EventoSimulado::campoAlterado('proxima_consulta', '15/09/2026 14:00'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->descricao)->toBe('Mover de FOLLOW 24 HRS para AGENDOU CONSULTA/PROCEDIMENTO')
        ->and($r->acoes[0]->endpoint)->toBe('/api/v4/leads/777001')
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 54917479, 'pipeline_id' => 6441483])
        ->and($r->acoes[1]->payload)->toBe(['_embedded' => ['tags' => []]])
        ->and($r->acoes[2]->tipo)->toBe('cancelar_reguas')
        ->and($r->acoes[2]->payload['kommo_lead_id'])->toBe(777001);
});

it('A2 casa na Dra Vanessa: move para 71591324', function () {
    $lead = leadAutomacao('luna', 110706663, ['tags' => ['Contato2']]);

    $r = $this->motor->avaliar(regraDoCatalogo('A2'), $lead, EventoSimulado::campoAlterado('proxima_consulta', '15/09/2026 14:00'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 71591324, 'pipeline_id' => 9219740])
        ->and($r->acoes[0]->descricao)->toBe('Mover de FU2 · AGEND. NÃO CONCLUÍDO para 1 AGENDOU CONSULTA');
});

it('A2 não casa com lead fora de FUP / Não agendou', function () {
    $lead = leadAutomacao('duda', 56708683);

    $r = $this->motor->avaliar(regraDoCatalogo('A2'), $lead, EventoSimulado::campoAlterado('proxima_consulta', '15/09/2026 14:00'));

    expect($r->gatilhoCasou)->toBeTrue()
        ->and($r->condicoes[0]->ok)->toBeFalse()
        ->and($r->casou())->toBeFalse()
        ->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------------ A3

it('A3 casa: Comparecimento = Compareceu move para Realizou consulta (56708683)', function () {
    $lead = leadAutomacao('duda', 54917479);

    $r = $this->motor->avaliar(regraDoCatalogo('A3'), $lead, EventoSimulado::campoAlterado('comparecimento', 'Compareceu'));

    expect($r->casou())->toBeTrue()
        ->and($r->gatilhoMotivo)->toBe('Campo Comparecimento mudou para “Compareceu”.')
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 56708683, 'pipeline_id' => 6441483]);
});

it('A3 não casa quando o Comparecimento muda para Faltou', function () {
    $lead = leadAutomacao('duda', 54917479);

    $r = $this->motor->avaliar(regraDoCatalogo('A3'), $lead, EventoSimulado::campoAlterado('comparecimento', 'Faltou'));

    expect($r->casou())->toBeFalse()
        ->and($r->gatilhoMotivo)->toContain('a regra espera “Compareceu”')
        ->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------------ A4

it('A4 casa na Dra Vanessa: proposta preenchida em Realizou consulta move para 71591332', function () {
    $lead = leadAutomacao('luna', 73758392);

    $r = $this->motor->avaliar(regraDoCatalogo('A4'), $lead, EventoSimulado::campoAlterado('valor_proposta', '18500'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 71591332, 'pipeline_id' => 9219740]);
});

it('A4 não casa com lead que ainda não realizou a consulta', function () {
    $lead = leadAutomacao('luna', 71591324);

    $r = $this->motor->avaliar(regraDoCatalogo('A4'), $lead, EventoSimulado::campoAlterado('valor_proposta', '18500'));

    expect($r->casou())->toBeFalse()
        ->and($r->condicoes[0]->motivo)->toContain('fora de Consulta realizada')
        ->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------------ A5

it('A5 casa: falta move para Não agendou, põe RESGATECONSULTA e inicia a régua', function () {
    $lead = leadAutomacao('duda', 54917479);

    $r = $this->motor->avaliar(regraDoCatalogo('A5'), $lead, EventoSimulado::campoAlterado('comparecimento', 'Faltou'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 69609380, 'pipeline_id' => 6441483])
        ->and($r->acoes[1]->payload)->toBe(['_embedded' => ['tags' => [['name' => 'RESGATECONSULTA']]]])
        ->and($r->acoes[2]->tipo)->toBe('iniciar_regua')
        ->and($r->acoes[2]->payload['status_id'])->toBe(69609380);
});

it('A5 não casa quando o paciente remarcou', function () {
    $lead = leadAutomacao('duda', 54917479);

    $r = $this->motor->avaliar(regraDoCatalogo('A5'), $lead, EventoSimulado::campoAlterado('comparecimento', 'Remarcou'));

    expect($r->casou())->toBeFalse()->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------------ A6

it('A6 casa: move para 142, cria card no Fluxo cirurgias com o mesmo contato, tarefa e recebível', function () {
    $lead = leadAutomacao('duda', 54917483, [
        'name' => 'Fernanda Rocha',
        'valorTotal' => '32.000,00',
        'cirurgia' => 'Mamoplastia de aumento',
        'dataCirurgia' => '20/10/2026',
    ]);

    $r = $this->motor->avaliar(
        regraDoCatalogo('A6'),
        $lead,
        EventoSimulado::campoAlterado('data_assinatura', '10/09/2026'),
        ['hospital' => 'Hospital Unimed Sorocaba'],
    );

    expect($r->casou())->toBeTrue()
        ->and($r->acoes)->toHaveCount(4);

    [$mover, $card, $tarefa, $recebivel] = $r->acoes;

    expect($mover->payload)->toBe(['status_id' => 142, 'pipeline_id' => 6441483])
        ->and($card->metodo)->toBe('POST')
        ->and($card->endpoint)->toBe('/api/v4/leads')
        ->and($card->payload[0]['pipeline_id'])->toBe(11380984)
        ->and($card->payload[0])->not->toHaveKey('status_id')
        ->and($card->payload[0]['name'])->toBe('Fernanda Rocha')
        ->and($card->payload[0]['price'])->toBe(32000)
        ->and($card->payload[0]['_embedded']['contacts'])->toBe([['id' => 555001]])
        ->and(array_column($card->payload[0]['custom_fields_values'], 'field_id'))
        ->toBe(['{Cirurgia}', '{Data da cirurgia}', '{Hospital}', '{Valor total}'])
        ->and($card->observacao)->toContain('Anestesista e Prótese')
        ->and($tarefa->endpoint)->toBe('/api/v4/tasks')
        ->and($tarefa->payload[0]['text'])->toBe('Iniciar pré-operatório')
        ->and($tarefa->payload[0]['entity_type'])->toBe('leads')
        ->and($recebivel->payload['valor'])->toBe(32000.0);
});

it('A6 também casa pela tag CONTRATO', function () {
    $lead = leadAutomacao('luna', 71591332);

    $r = $this->motor->avaliar(regraDoCatalogo('A6'), $lead, EventoSimulado::tagAdicionada('CONTRATO'));

    expect($r->casou())->toBeTrue()
        ->and($r->gatilhoMotivo)->toBe('Tag CONTRATO adicionada.')
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 142, 'pipeline_id' => 9219740])
        ->and($r->acoes[1]->payload[0]['pipeline_id'])->toBe(11380984);
});

it('A6 não casa com outra tag', function () {
    $lead = leadAutomacao('duda', 54917483);

    $r = $this->motor->avaliar(regraDoCatalogo('A6'), $lead, EventoSimulado::tagAdicionada('SINAL'));

    expect($r->casou())->toBeFalse()->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------------ A7

it('A7 casa no Dr Eduardo: sinal pago sai de Aguardando sinal para 54917479 e põe PAGAMENTOOK', function () {
    $lead = leadAutomacao('duda', 75912472, ['statusSinal' => 'Pendente', 'tags' => ['SINAL']]);

    $r = $this->motor->avaliar(regraDoCatalogo('A7'), $lead, EventoSimulado::campoAlterado('status_sinal', 'Pago'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->descricao)->toBe('Mover de AGUARDANDO SINAL para AGENDOU CONSULTA/PROCEDIMENTO')
        ->and($r->acoes[0]->payload)->toBe(['status_id' => 54917479, 'pipeline_id' => 6441483])
        ->and($r->acoes[1]->payload)->toBe(['_embedded' => ['tags' => [['name' => 'SINAL'], ['name' => 'PAGAMENTOOK']]]]);
});

it('A7 não casa na Dra Vanessa: regra de outro pipeline', function () {
    $lead = leadAutomacao('luna', 71591324);

    $r = $this->motor->avaliar(regraDoCatalogo('A7'), $lead, EventoSimulado::campoAlterado('status_sinal', 'Pago'));

    expect($r->pipelineOk)->toBeFalse()
        ->and($r->casou())->toBeFalse()
        ->and($r->motivo())->toContain('A regra vale para Dr Eduardo')
        ->and($r->acoes)->toBe([]);
});

// ------------------------------------------------------------ transversais

it('regra de outro pipeline não casa mesmo com o evento certo', function () {
    $regra = regraDoCatalogo('A3');
    $regra->update(['pipeline_ids' => [(int) config('kommo.pipelines.luna')]]);

    $r = $this->motor->avaliar($regra, leadAutomacao('duda', 54917479), EventoSimulado::campoAlterado('comparecimento', 'Compareceu'));

    expect($r->casou())->toBeFalse()
        ->and($r->pipelineOk)->toBeFalse()
        ->and($r->pipelineMotivo)->toContain('o lead está no pipeline Dr Eduardo');
});

it('anti-loop: evento gerado pela própria integração é ignorado', function () {
    $evento = EventoSimulado::campoAlterado('comparecimento', 'Compareceu')->daIntegracao();

    $r = $this->motor->avaliar(regraDoCatalogo('A3'), leadAutomacao('duda', 54917479), $evento);

    expect($r->ignoradoPorLoop)->toBeTrue()
        ->and($r->casou())->toBeFalse()
        ->and($r->motivo())->toBe(MotorDeAutomacoes::MOTIVO_ANTI_LOOP)
        ->and($r->acoes)->toBe([]);
});

it('não move quem já está na etapa de destino', function () {
    $r = $this->motor->avaliar(regraDoCatalogo('A3'), leadAutomacao('duda', 56708683), EventoSimulado::campoAlterado('comparecimento', 'Compareceu'));

    expect($r->casou())->toBeTrue()
        ->and($r->acoes[0]->aplicavel)->toBeFalse()
        ->and($r->acoes[0]->observacao)->toContain('já estava na etapa de destino')
        ->and($r->acoesAplicaveis())->toBe([]);
});

it('sem evento, avalia o estado atual do lead', function () {
    $lead = leadAutomacao('duda', 75912472, ['statusSinal' => 'Pago']);

    $r = $this->motor->avaliar(regraDoCatalogo('A7'), $lead);

    expect($r->casou())->toBeTrue()
        ->and($r->gatilhoMotivo)->toContain('estado atual');
});

it('ações convertem de e para o formato do Builder sem perder nada', function () {
    $acoes = AutomacoesDemoSeeder::regras()['A6']['acoes'];

    $idaEVolta = NormalizadorDeRegra::doBuilder(NormalizadorDeRegra::paraBuilder($acoes));

    expect($idaEVolta)->toBe($acoes);
});
