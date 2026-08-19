<?php

use App\Services\Kommo\DTO\LeadData;

const FIELD_IDS = [
    'origem' => 101,
    'temperatura' => 102,
    'procedimento' => 103,
    'urgencia' => 104,
    'score' => 105,
    'instagram' => 106,
];

it('converte o lead cru da API resolvendo os campos das agentes', function () {
    $lead = LeadData::fromApi(fakeLead([
        'id' => 123,
        'name' => 'IG @fulana',
        'origem' => 'Tráfego pago',
        'temperatura' => 'Quente',
        'procedimento' => 'Mommy Makeover',
        'score' => '85',
        'instagram' => 'fulana',
    ]), FIELD_IDS);

    expect($lead->id)->toBe(123)
        ->and($lead->origem)->toBe('Tráfego pago')
        ->and($lead->temperatura)->toBe('Quente')
        ->and($lead->procedimento)->toBe('Mommy Makeover')
        ->and($lead->score)->toBe(85.0)
        ->and($lead->instagramHandle())->toBe('@fulana')
        ->and($lead->geradoPelaAgente())->toBeTrue();
});

it('identifica lead cadastrado à mão pela equipe', function () {
    $lead = LeadData::fromApi(fakeLead(['name' => 'Maria da Silva']), FIELD_IDS);

    expect($lead->geradoPelaAgente())->toBeFalse();
});

it('extrai o @ do nome quando o campo Instagram está vazio', function () {
    $lead = LeadData::fromApi(fakeLead(['name' => 'IG @beltrana', 'instagram' => null]), FIELD_IDS);

    expect($lead->instagramHandle())->toBe('@beltrana');
});

it('deixa campos ausentes como null em vez de inventar valor', function () {
    $lead = LeadData::fromApi(fakeLead([
        'origem' => null,
        'temperatura' => null,
        'procedimento' => null,
        'score' => null,
    ]), FIELD_IDS);

    expect($lead->origem)->toBeNull()
        ->and($lead->temperatura)->toBeNull()
        ->and($lead->procedimento)->toBeNull()
        ->and($lead->score)->toBeNull();
});

it('reconhece ganho e perdido pelos status padrão do Kommo', function () {
    $ganho = LeadData::fromApi(fakeLead(['status_id' => 142]), FIELD_IDS);
    $perdido = LeadData::fromApi(fakeLead(['status_id' => 143]), FIELD_IDS);

    expect($ganho->ganho())->toBeTrue()
        ->and($perdido->perdido())->toBeTrue();
});

it('monta o link direto para o lead no Kommo', function () {
    $lead = LeadData::fromApi(fakeLead(['id' => 987654]), FIELD_IDS);

    expect($lead->kommoUrl())->toBe('https://ottoboni.kommo.com/leads/detail/987654');
});
