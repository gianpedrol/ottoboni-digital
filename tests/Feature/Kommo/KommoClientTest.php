<?php

use App\Models\KommoApiCall;
use App\Services\Kommo\KommoBlockedException;
use App\Services\Kommo\KommoClient;
use App\Services\Kommo\KommoException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('kommo.token', 'token-de-teste');
});

it('refaz a chamada com backoff quando recebe 429 e depois sucesso', function () {
    Http::fake([
        '*/leads*' => Http::sequence()
            ->push(['erro' => 'rate limit'], 429)
            ->push(kommoPage([fakeLead()], 'leads'), 200),
    ]);

    $body = app(KommoClient::class)->get('/leads');

    expect($body['_embedded']['leads'])->toHaveCount(1);
    Http::assertSentCount(2);
});

it('interrompe tudo quando recebe 403 (possível bloqueio de IP)', function () {
    Http::fake(['*' => Http::response('Forbidden', 403)]);

    app(KommoClient::class)->get('/leads');
})->throws(KommoBlockedException::class);

it('desiste depois de esgotar as tentativas em erro 5xx', function () {
    Http::fake(['*' => Http::response('Server error', 500)]);

    try {
        app(KommoClient::class)->get('/leads');
        $this->fail('Deveria ter lançado KommoException.');
    } catch (KommoException) {
        Http::assertSentCount((int) config('kommo.retries'));
    }
});

it('trata 204 como página vazia', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    expect(app(KommoClient::class)->get('/leads'))->toBe([]);
});

it('pagina automaticamente enquanto houver link next', function () {
    Http::fake([
        '*/leads*' => Http::sequence()
            ->push(kommoPage([fakeLead(), fakeLead()], 'leads', hasNext: true), 200)
            ->push(kommoPage([fakeLead()], 'leads'), 200),
    ]);

    $itens = iterator_to_array(app(KommoClient::class)->paginate('/leads', [], 'leads'), false);

    expect($itens)->toHaveCount(3);
    Http::assertSentCount(2);
});

it('respeita o teto de páginas quando informado', function () {
    Http::fake([
        '*/leads*' => Http::response(kommoPage([fakeLead()], 'leads', hasNext: true), 200),
    ]);

    $itens = iterator_to_array(app(KommoClient::class)->paginate('/leads', [], 'leads', maxPages: 2), false);

    expect($itens)->toHaveCount(2);
    Http::assertSentCount(2);
});

it('registra cada chamada em kommo_api_calls', function () {
    Http::fake(['*/leads*' => Http::response(kommoPage([fakeLead()], 'leads'), 200)]);

    app(KommoClient::class)->get('/leads');

    expect(KommoApiCall::query()->count())->toBe(1)
        ->and(KommoApiCall::query()->first()->itens)->toBe(1);
});

it('falha com mensagem clara quando o token não está configurado', function () {
    config()->set('kommo.token', null);

    app(KommoClient::class)->get('/leads');
})->throws(KommoException::class, 'KOMMO_TOKEN');
