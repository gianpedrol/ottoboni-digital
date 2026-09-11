<?php

use App\Enums\IaApprovalStatus;
use App\Enums\IaGateModo;
use App\Enums\IaGrauEdicao;
use App\Enums\IaIntent;
use App\Enums\IaMotivoFila;
use App\Models\IaGateSetting;
use App\Services\Ia\PortaoDeAprovacao;

/**
 * O portão é o mecanismo de segurança do projeto. Se ele liberar o que não
 * devia, a resposta ruim vai direto para o Instagram da cliente — por isso
 * cada regra tem teste próprio.
 */
beforeEach(function () {
    $this->luna = criarMedicoLuna();
    $this->portao = app(PortaoDeAprovacao::class);
    $this->cfg = IaGateSetting::paraAgente($this->luna->id);
});

it('segura tudo no modo treinamento', function () {
    $this->cfg->update(['modo' => IaGateModo::Treinamento]);

    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->motivo)->toBe(IaMotivoFila::ModoTreinamento);
});

it('nunca libera "não sei responder", mesmo com acurácia perfeita', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoPorAcuracia, 'min_amostras' => 1]);

    // 50 revisões perfeitas: a nota geral está em 1,0.
    for ($i = 0; $i < 50; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::SemEdicao);
    }

    $d = $this->portao->decidir($this->luna->id, IaIntent::NaoSei);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->motivo)->toBe(IaMotivoFila::NaoSei)
        ->and($d->dmEspera)->toBeTrue();
});

it('nunca libera "não sei" nem no modo automático total', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoTotal]);

    expect($this->portao->decidir($this->luna->id, IaIntent::NaoSei)->enviarDireto)->toBeFalse()
        ->and($this->portao->decidir($this->luna->id, IaIntent::Consulta)->enviarDireto)->toBeTrue();
});

it('não libera assunto com poucas amostras, mesmo sem nenhum erro', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoPorAcuracia, 'min_amostras' => 30]);

    for ($i = 0; $i < 10; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::SemEdicao);
    }

    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->motivo)->toBe(IaMotivoFila::AmostrasInsuficientes)
        ->and($d->detalhes['amostras'])->toBe(10);
});

it('libera o assunto que passou dos 90% com amostras suficientes', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoPorAcuracia, 'min_amostras' => 30, 'janela' => 50]);

    // 45 sem edição + 5 com ajuste leve = média 0,98
    for ($i = 0; $i < 45; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::SemEdicao);
    }

    for ($i = 0; $i < 5; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::Leve);
    }

    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->enviarDireto)->toBeTrue()
        ->and($d->detalhes['acuracia'])->toBeGreaterThanOrEqual(0.9);
});

it('não libera assunto abaixo do limiar', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoPorAcuracia, 'min_amostras' => 10]);

    // metade refeita: média 0,5
    for ($i = 0; $i < 10; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::SemEdicao);
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::Refeita);
    }

    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->motivo)->toBe(IaMotivoFila::AcuraciaInsuficiente);
});

it('segura o assunto quando existe qualquer rejeição na janela', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoPorAcuracia, 'min_amostras' => 10, 'janela' => 50]);

    for ($i = 0; $i < 49; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::SemEdicao);
    }

    // uma única rejeição basta: resposta que não deveria existir é grave
    revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::Refeita, IaApprovalStatus::Rejeitado);

    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->detalhes['rejeitadas'])->toBe(1);
});

it('aciona o freio de mão e volta tudo para a fila quando a nota geral cai', function () {
    $this->cfg->update([
        'modo' => IaGateModo::AutoPorAcuracia,
        'min_amostras' => 5,
        'kill_switch_acuracia' => 0.85,
    ]);

    // teste_genetico impecável, mas a nota GERAL afunda por causa de outro assunto
    for ($i = 0; $i < 10; $i++) {
        revisaoFechada($this->luna, IaIntent::TesteGenetico, IaGrauEdicao::SemEdicao);
    }

    for ($i = 0; $i < 20; $i++) {
        revisaoFechada($this->luna, IaIntent::QueixaPele, IaGrauEdicao::Refeita);
    }

    $d = $this->portao->decidir($this->luna->id, IaIntent::TesteGenetico);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->motivo)->toBe(IaMotivoFila::AcuraciaInsuficiente)
        ->and($d->detalhes)->toHaveKey('acuracia_geral');
});

it('não conta o que saiu automático na acurácia', function () {
    $this->cfg->update(['modo' => IaGateModo::AutoPorAcuracia, 'min_amostras' => 5]);

    // 20 auto_enviado não podem virar nota: ninguém revisou
    for ($i = 0; $i < 20; $i++) {
        revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::SemEdicao, IaApprovalStatus::AutoEnviado);
    }

    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->enviarDireto)->toBeFalse()
        ->and($d->motivo)->toBe(IaMotivoFila::AmostrasInsuficientes);
});

it('devolve o modelo configurado e nunca um modelo escolhido pela tela', function () {
    $d = $this->portao->decidir($this->luna->id, IaIntent::Consulta);

    expect($d->modelo)->toBe($this->cfg->modelo);
});
