<?php

use App\Enums\IaGrauEdicao;
use App\Services\Ia\MedidorDeEdicao;

/**
 * A acurácia do projeto inteiro sai deste medidor, então ele precisa acertar
 * o essencial: texto idêntico é 1,0; trocar um emoji não é edição; reescrever
 * o conteúdo é 0.
 */
beforeEach(function () {
    $this->medidor = new MedidorDeEdicao;
});

it('dá nota cheia quando o humano não mexe no texto', function () {
    $texto = 'A consulta custa R$ 900,00 e dura cerca de 1 hora.';

    $r = $this->medidor->medir($texto, $texto);

    expect($r['grau'])->toBe(IaGrauEdicao::SemEdicao)
        ->and($r['score'])->toBe(1.0)
        ->and($r['similaridade'])->toBe(1.0);
});

it('ignora diferença só de emoji, pontuação e acento', function () {
    $r = $this->medidor->medir(
        'Olá! Que bom te ver por aqui 💛',
        'Ola, que bom te ver por aqui',
    );

    expect($r['grau'])->toBe(IaGrauEdicao::SemEdicao)
        ->and($r['score'])->toBe(1.0);
});

it('reconhece ajuste leve quando o humano corrige poucas palavras', function () {
    $r = $this->medidor->medir(
        'A consulta com a Dra. Vanessa custa R$ 900,00 e dura cerca de 1 hora. Posso te ajudar com o agendamento?',
        'A consulta com a Dra. Vanessa custa R$ 950,00 e dura cerca de 1 hora. Posso te ajudar com o agendamento?',
    );

    expect($r['grau'])->toBe(IaGrauEdicao::Leve)
        ->and($r['score'])->toBe(0.8);
});

it('dá zero quando a resposta é refeita do zero', function () {
    $r = $this->medidor->medir(
        'O Flor&Ser Raiz é o nosso programa de entrada e não inclui o teste.',
        'O teste genético faz parte do Flor&Ser Pleno. A coleta é presencial em Curitiba.',
    );

    expect($r['grau'])->toBe(IaGrauEdicao::Refeita)
        ->and($r['score'])->toBe(0.0);
});

it('dá zero quando a agente não produziu rascunho (caso "não sei")', function () {
    $r = $this->medidor->medir(null, 'Resposta escrita inteira pela equipe.');

    expect($r['grau'])->toBe(IaGrauEdicao::Refeita)
        ->and($r['score'])->toBe(0.0);
});

it('não é enganado por reordenação de frases', function () {
    // Só trocar a ordem mantém as palavras, mas muda o texto: não pode
    // contar como "aprovado sem editar".
    $r = $this->medidor->medir(
        'A coleta é em Curitiba. O teste faz parte do Pleno.',
        'O teste faz parte do Pleno. A coleta é em Curitiba.',
    );

    expect($r['grau'])->not->toBe(IaGrauEdicao::SemEdicao);
});
