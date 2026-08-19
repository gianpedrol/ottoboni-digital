<?php

use App\Services\Kommo\CustomFieldMap;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');

    Http::fake([
        '*/leads/custom_fields*' => Http::response(kommoFixture('custom_fields'), 200),
    ]);
});

it('resolve o id do campo pelo nome, sem depender de maiúsculas ou acentos', function () {
    $map = app(CustomFieldMap::class);

    expect($map->idFor('origem'))->toBe(101)
        ->and($map->idFor('temperatura'))->toBe(102)
        ->and($map->idFor('urgencia'))->toBe(104)
        ->and($map->idFor('score'))->toBe(105)
        ->and($map->idFor('instagram'))->toBe(106);
});

it('tolera variações de nome usando busca por "contém"', function () {
    // Config espera "Procedimento"; a conta tem "Procedimento de interesse".
    expect(app(CustomFieldMap::class)->idFor('procedimento'))->toBe(103);
});

it('busca o mapa uma única vez e reaproveita o cache', function () {
    $map = app(CustomFieldMap::class);

    $map->idFor('origem');
    $map->idFor('temperatura');
    $map->idFor('score');

    Http::assertSentCount(1);
});

it('devolve null para campo que não existe na conta', function () {
    config()->set('kommo.custom_fields.origem', 'Campo Que Nao Existe');

    expect(app(CustomFieldMap::class)->idFor('origem'))->toBeNull();
});
