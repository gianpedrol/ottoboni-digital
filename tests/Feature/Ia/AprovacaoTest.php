<?php

use App\Enums\IaApprovalStatus;
use App\Enums\IaGrauEdicao;
use App\Enums\IaIntent;
use App\Enums\IaMotivoFila;
use App\Jobs\EnviarRespostaAprovadaJob;
use App\Models\AuditLog;
use App\Models\IaCard;
use App\Models\IaExample;
use App\Models\User;
use App\Services\Ia\AprovadorDeResposta;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->luna = criarMedicoLuna();
    $this->revisor = User::factory()->create(['name' => 'Pietra']);
    $this->aprovador = app(AprovadorDeResposta::class);
});

it('não aprova sem aceite de responsabilidade', function () {
    $item = itemPendente($this->luna);

    expect(fn () => $this->aprovador->aprovar($item, null, 'texto', $this->revisor, aceite: false))
        ->toThrow(RuntimeException::class, 'responsabilidade');

    expect($item->refresh()->status)->toBe(IaApprovalStatus::Pendente);
    Queue::assertNothingPushed();
});

it('aprova sem edição, despacha o envio e não cria exemplo', function () {
    $item = itemPendente($this->luna);

    $this->aprovador->aprovar($item, $item->rascunho_comentario, $item->rascunho_dm, $this->revisor, aceite: true);

    $item->refresh();

    expect($item->status)->toBe(IaApprovalStatus::Aprovado)
        ->and($item->grau_edicao)->toBe(IaGrauEdicao::SemEdicao)
        ->and($item->score)->toBe(1.0)
        ->and($item->revisado_por)->toBe($this->revisor->id)
        ->and($item->responsabilidade_aceita)->toBeTrue();

    // aprovar 200 respostas idênticas não ensina nada e só incharia o prompt
    expect(IaExample::query()->count())->toBe(0);

    Queue::assertPushed(EnviarRespostaAprovadaJob::class);
});

it('vira exemplo de treino quando o humano corrige a resposta', function () {
    $item = itemPendente($this->luna);

    $this->aprovador->aprovar(
        $item,
        $item->rascunho_comentario,
        'O teste genético faz parte do Flor&Ser Pleno e a coleta é presencial em Curitiba.',
        $this->revisor,
        aceite: true,
        observacao: 'ela ofereceu o programa errado',
    );

    $item->refresh();

    expect($item->grau_edicao)->toBe(IaGrauEdicao::Refeita)
        ->and($item->score)->toBe(0.0);

    $exemplo = IaExample::query()->firstOrFail();

    expect($exemplo->resposta)->toContain('Flor&Ser Pleno')
        ->and($exemplo->resposta_rejeitada)->toContain('R$ 900,00')  // o contra-exemplo
        ->and($exemplo->approval_id)->toBe($item->id);
});

it('transforma em card na base a resposta de um "não sei"', function () {
    $item = itemPendente($this->luna, [
        'intent' => IaIntent::NaoSei,
        'motivo_fila' => IaMotivoFila::NaoSei,
        'comentario_texto' => 'vocês fazem tratamento para melasma na gravidez?',
        'rascunho_comentario' => null,
        'rascunho_dm' => null,
    ]);

    $this->aprovador->aprovar(
        $item,
        null,
        'Fazemos sim, com protocolo seguro para gestantes. A avaliação é presencial.',
        $this->revisor,
        aceite: true,
    );

    $card = IaCard::query()->firstOrFail();

    expect($card->pergunta)->toBe('vocês fazem tratamento para melasma na gravidez?')
        ->and($card->resposta)->toContain('protocolo seguro')
        ->and($card->status)->toBe('validado')
        ->and($card->origem)->toBe('painel_aprovacao');

    // e entra como exemplo prioritário: é o buraco da base sendo tapado
    expect(IaExample::query()->firstOrFail()->prioridade)->toBe(10);
});

it('recusa aprovar um "não sei" com os dois campos em branco', function () {
    // Sem rascunho da agente e sem texto do humano não há o que enviar.
    // O caminho certo para "a resposta é o silêncio" é rejeitar, não aprovar vazio.
    $item = itemPendente($this->luna, [
        'intent' => IaIntent::NaoSei,
        'motivo_fila' => IaMotivoFila::NaoSei,
        'rascunho_comentario' => null,
        'rascunho_dm' => null,
    ]);

    expect(fn () => $this->aprovador->aprovar($item, '', '', $this->revisor, aceite: true))
        ->toThrow(RuntimeException::class, 'Não há texto para enviar');

    expect($item->refresh()->status)->toBe(IaApprovalStatus::Pendente);
    Queue::assertNothingPushed();
});

it('usa o rascunho quando o campo fica em branco', function () {
    $item = itemPendente($this->luna);

    $this->aprovador->aprovar($item, null, null, $this->revisor, aceite: true);

    expect($item->refresh()->final_dm)->toBe($item->rascunho_dm);
});

it('não aprova duas vezes o mesmo item', function () {
    $item = itemPendente($this->luna);

    $this->aprovador->aprovar($item, null, null, $this->revisor, aceite: true);

    expect(fn () => $this->aprovador->aprovar($item->refresh(), null, null, $this->revisor, aceite: true))
        ->toThrow(RuntimeException::class);
});

it('rejeita sem enviar nada e conta como erro na acurácia', function () {
    $item = itemPendente($this->luna);

    $this->aprovador->rejeitar($item, $this->revisor, 'respondeu sem nexo um comentário de aniversário');

    $item->refresh();

    expect($item->status)->toBe(IaApprovalStatus::Rejeitado)
        ->and($item->score)->toBe(0.0)
        ->and($item->final_dm)->toBeNull();

    Queue::assertNothingPushed();
});

it('registra na auditoria quem aprovou e o quanto editou', function () {
    $item = itemPendente($this->luna);

    $this->aprovador->aprovar($item, null, 'texto completamente diferente do rascunho', $this->revisor, aceite: true);

    $log = AuditLog::query()->where('acao', 'ia.aprovou')->firstOrFail();

    expect($log->user_id)->toBe($this->revisor->id)
        ->and($log->contexto['approval_id'])->toBe($item->id)
        ->and($log->contexto['grau_edicao'])->toBe('refeita');
});
