<?php

use App\Enums\IaApprovalStatus;
use App\Enums\IaGateModo;
use App\Enums\IaIntent;
use App\Models\IaApproval;
use App\Models\IaCard;
use App\Models\IaGateSetting;
use App\Models\WebhookLog;
use Database\Seeders\IaGuardrailSeeder;

beforeEach(function () {
    config()->set('painel.ia.webhook_secret', 'segredo-teste');
    $this->luna = criarMedicoLuna();
});

/** @return array<string, mixed> */
function rascunho(array $overrides = []): array
{
    return [
        'agente' => 'luna',
        'canal' => 'comentario',
        'intent' => 'consulta',
        'confianca' => 0.85,
        'ig_id' => '9988776655',
        'ig_username' => 'paciente_teste',
        'comment_id' => 'c_123',
        'comentario_texto' => 'quanto custa a consulta?',
        'rascunho_comentario' => 'Te chamei no direct!',
        'rascunho_dm' => 'A consulta custa R$ 900,00.',
        'cards_usados' => [1, 4],
        ...$overrides,
    ];
}

it('recusa sem assinatura válida', function () {
    postIa('/api/ia/rascunho', rascunho(), assinatura: 'sha256=errado')
        ->assertStatus(401);

    expect(IaApproval::query()->count())->toBe(0)
        ->and(WebhookLog::query()->where('assinatura_ok', false)->count())->toBe(1);
});

it('enfileira o rascunho no modo treinamento e não manda enviar', function () {
    $resposta = postIa('/api/ia/rascunho', rascunho())->assertOk();

    $resposta->assertJson([
        'enviar_direto' => false,
        'motivo' => 'modo_treinamento',
    ]);

    $item = IaApproval::query()->firstOrFail();

    expect($item->status)->toBe(IaApprovalStatus::Pendente)
        ->and($item->intent)->toBe(IaIntent::Consulta)
        ->and($item->rascunho_dm)->toBe('A consulta custa R$ 900,00.')
        ->and($item->final_dm)->toBeNull()          // nada foi enviado
        ->and($item->expira_em)->not->toBeNull()
        ->and($resposta->json('approval_id'))->toBe($item->id);
});

it('não duplica o mesmo comentário quando o webhook da Meta repete', function () {
    postIa('/api/ia/rascunho', rascunho())->assertOk();
    $segunda = postIa('/api/ia/rascunho', rascunho())->assertOk();

    expect(IaApproval::query()->count())->toBe(1)
        ->and($segunda->json('ja_na_fila'))->toBeTrue();
});

it('pede DM de espera na primeira vez e não na repetição', function () {
    expect(postIa('/api/ia/rascunho', rascunho())->json('enviar_dm_espera'))->toBeTrue();

    IaApproval::query()->update(['dm_espera_enviada' => true]);

    expect(postIa('/api/ia/rascunho', rascunho())->json('enviar_dm_espera'))->toBeFalse();
});

it('manda enviar quando o assunto está liberado', function () {
    IaGateSetting::paraAgente($this->luna->id)->update(['modo' => IaGateModo::AutoTotal]);

    postIa('/api/ia/rascunho', rascunho())
        ->assertOk()
        ->assertJson(['enviar_direto' => true, 'motivo' => 'auto']);

    expect(IaApproval::query()->count())->toBe(0); // nada na fila
});

it('registra no histórico o que o n8n já enviou automático', function () {
    IaGateSetting::paraAgente($this->luna->id)->update(['modo' => IaGateModo::AutoTotal]);

    postIa('/api/ia/rascunho', rascunho(['ja_enviado' => true]))->assertOk();

    $item = IaApproval::query()->firstOrFail();

    expect($item->status)->toBe(IaApprovalStatus::AutoEnviado)
        ->and($item->score)->toBeNull()   // não entra na acurácia
        ->and($item->final_dm)->toBe('A consulta custa R$ 900,00.');
});

it('devolve agente desconhecido com 404', function () {
    postIa('/api/ia/rascunho', rascunho(['agente' => 'inexistente']))->assertStatus(404);
});

it('entrega o contexto com prompt, regras e cards', function () {
    IaGuardrailSeeder::class;
    (new IaGuardrailSeeder)->run();

    IaCard::query()->create([
        'doctor_id' => $this->luna->id,
        'pergunta' => 'qual o valor da consulta?',
        'resposta' => 'R$ 900,00',
        'status' => 'validado',
    ]);

    $resposta = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    expect($resposta->json('modelo'))->toBe('gpt-4.1')
        ->and($resposta->json('guardrails'))->toHaveCount(10)
        ->and($resposta->json('cards'))->toHaveCount(1)
        ->and($resposta->json('system_prompt'))->toContain('REGRAS INEGOCIÁVEIS');
});
