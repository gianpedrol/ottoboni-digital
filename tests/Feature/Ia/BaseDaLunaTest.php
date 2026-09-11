<?php

use App\Console\Commands\IaImportarSupabase;
use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Filament\Ia\Pages\ConfiguracaoDaIa;
use App\Models\IaCard;
use App\Models\IaPromptVersion;
use App\Models\IaTrigger;
use App\Models\User;
use App\Support\AgenteSelecionado;
use App\Support\SegredosDoPainel;
use Database\Seeders\LunaBaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('painel.ia.webhook_secret', 'segredo-teste');
    $this->luna = criarMedicoLuna();
});

// ---------------------------------------------------------------------------
// Seeder: a v1 é o que roda hoje no n8n, marcada como não revisada
// ---------------------------------------------------------------------------

it('carrega a v1 da Luna como versão ativa sem aceite de responsabilidade', function () {
    (new LunaBaseSeeder)->run();

    $v1 = IaPromptVersion::query()->where('doctor_id', $this->luna->id)->sole();

    expect($v1->versao)->toBe(1)
        ->and($v1->ativo)->toBeTrue()
        ->and($v1->aceite_responsabilidade)->toBeFalse()
        ->and($v1->motivo)->toBe(LunaBaseSeeder::MOTIVO_V1)
        ->and(array_keys($v1->blocos))->toBe(array_keys(IaPromptVersion::BLOCOS))
        ->and($v1->blocos['PERSONA'])->toContain('Você é a Luna')
        ->and($v1->blocos['BASE'])->toContain('R$ 710,00')
        ->and($v1->blocos['COMENTARIO_INSTRUCOES'])->toContain('comentou em um post')
        ->and($v1->blocos['HANDOFF_MSG'])->toBe('Já estou chamando a Pietra aqui pra te ajudar.');

    // O contrato técnico de saída (JSON) fica no n8n, não no painel.
    foreach ($v1->blocos as $texto) {
        expect($texto)->not->toContain('FORMATO DE SAIDA');
    }

    expect(IaTrigger::query()->where('doctor_id', $this->luna->id)->where('termo', 'chique')->exists())->toBeTrue();
});

it('não duplica a v1 quando rodado de novo', function () {
    (new LunaBaseSeeder)->run();
    (new LunaBaseSeeder)->run();

    expect(IaPromptVersion::query()->where('doctor_id', $this->luna->id)->count())->toBe(1)
        ->and(IaTrigger::query()->where('doctor_id', $this->luna->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Contexto por canal
// ---------------------------------------------------------------------------

it('monta o prompt do Direct e o de comentário a partir da mesma versão', function () {
    (new LunaBaseSeeder)->run();

    $direct = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();
    $comentario = postIa('/api/ia/contexto', ['agente' => 'luna', 'canal' => 'comentario'])->assertOk();

    expect($direct->json('canal'))->toBe('direct')
        ->and($direct->json('prompt_revisado'))->toBeFalse()
        ->and($direct->json('system_prompt'))->toContain('### Quem a agente é')
        ->and($direct->json('system_prompt'))->toContain('### Informações da clínica')
        ->and($direct->json('system_prompt'))->not->toContain('comentou em um post')
        ->and($direct->json('system_prompt'))->toContain('REGRAS INEGOCIÁVEIS');

    expect($comentario->json('canal'))->toBe('comentario')
        ->and($comentario->json('system_prompt'))->toContain('comentou em um post')
        ->and($comentario->json('system_prompt'))->toContain('Mensagem ao passar para a equipe')
        ->and($comentario->json('system_prompt'))->not->toContain('### Informações da clínica')
        ->and($comentario->json('system_prompt'))->toContain('REGRAS INEGOCIÁVEIS')
        ->and($comentario->json('prompt_version_id'))->toBe($direct->json('prompt_version_id'));
});

it('rejeita canal desconhecido', function () {
    postIa('/api/ia/contexto', ['agente' => 'luna', 'canal' => 'whatsapp'])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Cards no formato que o n8n já usa
// ---------------------------------------------------------------------------

it('entrega os cards no texto exato que o fluxo do n8n monta hoje', function () {
    IaCard::query()->create([
        'doctor_id' => $this->luna->id,
        'codigo' => 'programas.raiz',
        'modulo' => 'programas',
        'categoria' => 'Investimento do Flor&Ser Raiz',
        'pergunta' => 'quanto custa o raiz?',
        'perguntas_equivalentes' => ['quanto custa o raiz?', 'valor da consulta'],
        'resposta' => 'O investimento é R$ 710,00.',
        'resposta_detalhada' => 'Nunca fale do valor sem dizer o que inclui.',
        'status' => 'validado',
    ]);

    IaCard::query()->create([
        'doctor_id' => $this->luna->id,
        'codigo' => 'agenda.parcelamento',
        'categoria' => 'Parcelamento',
        'pergunta' => 'parcela em quantas vezes?',
        'perguntas_equivalentes' => ['parcela em quantas vezes?'],
        'resposta' => 'a definir',
        'status' => 'pendente',
    ]);

    IaCard::query()->create([
        'doctor_id' => $this->luna->id,
        'codigo' => 'local.endereco',
        'categoria' => 'Endereço',
        'pergunta' => 'onde fica?',
        'resposta' => 'Rua X',
        'status' => 'validado',
        'ativo' => false,
    ]);

    $resposta = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    $texto = $resposta->json('cards_texto');

    expect($texto)->toStartWith('CARDS OFICIAIS DA BASE DE CONHECIMENTO.')
        ->and($texto)->toContain("[programas.raiz] Investimento do Flor&Ser Raiz\nPerguntas tipicas: quanto custa o raiz? | valor da consulta\nResposta oficial: O investimento é R$ 710,00.\nDetalhe e regra de uso: Nunca fale do valor sem dizer o que inclui.")
        ->and($texto)->toContain("ASSUNTOS PENDENTES, nunca responda, use a mensagem de espera e action escalar:\n- Parcelamento (ex.: parcela em quantas vezes?)")
        ->and($texto)->not->toContain('local.endereco');

    // Cards desativados não vão nem na lista estruturada.
    expect(collect($resposta->json('cards'))->pluck('codigo')->all())->toBe(['agenda.parcelamento', 'programas.raiz']);
});

it('devolve cards_texto vazio quando não há card', function () {
    $resposta = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    expect($resposta->json('cards_texto'))->toBe('')
        ->and($resposta->json('cards'))->toBe([]);
});

// ---------------------------------------------------------------------------
// Importação do Supabase com as colunas reais de luna_cards
// ---------------------------------------------------------------------------

it('importa luna_cards com código, módulo, perguntas equivalentes e status', function () {
    config()->set('painel.ia.supabase_url', 'https://supabase.test');
    config()->set('painel.ia.supabase_key', 'chave-de-teste');

    Http::fake([
        'https://supabase.test/rest/v1/luna_cards*' => Http::response([
            [
                'id' => 'programas.raiz', 'conta' => 'luna', 'modulo' => 'programas',
                'categoria' => 'Flor&Ser Raiz', 'perguntas_equivalentes' => ['quanto custa?', 'valor'],
                'resposta_curta' => 'R$ 710,00', 'resposta_detalhada' => 'diga o que inclui',
                'status' => 'validado', 'bloqueado' => false,
            ],
            [
                'id' => 'local.endereco', 'conta' => 'luna', 'modulo' => 'local',
                'categoria' => 'Endereço', 'perguntas_equivalentes' => '["onde fica?"]',
                'resposta_curta' => 'Rua Frederico Cantarelli, 472', 'resposta_detalhada' => null,
                'status' => 'validado', 'bloqueado' => false,
            ],
            [
                'id' => 'agenda.parcelamento', 'conta' => 'luna', 'modulo' => 'agenda',
                'categoria' => 'Parcelamento', 'perguntas_equivalentes' => ['parcela?'],
                'resposta_curta' => 'a definir', 'status' => 'pendente', 'bloqueado' => false,
            ],
            [
                'id' => 'x.bloqueado', 'conta' => 'luna', 'categoria' => 'Bloqueado',
                'perguntas_equivalentes' => ['?'], 'resposta_curta' => 'nada', 'status' => 'rascunho', 'bloqueado' => true,
            ],
            [
                'id' => 'x.vazio', 'conta' => 'luna', 'categoria' => 'Sem resposta',
                'perguntas_equivalentes' => ['?'], 'resposta_curta' => '', 'status' => 'validado',
            ],
        ]),
        'https://supabase.test/rest/v1/luna_gatilhos*' => Http::response(['message' => 'relation does not exist'], 404),
    ]);

    Artisan::call('ia:importar-supabase', ['agente' => 'luna']);

    $cards = IaCard::query()->where('doctor_id', $this->luna->id)->orderBy('codigo')->get()->keyBy('codigo');

    expect($cards)->toHaveCount(4)
        ->and($cards['programas.raiz']->modulo)->toBe('programas')
        ->and($cards['programas.raiz']->pergunta)->toBe('quanto custa?')
        ->and($cards['programas.raiz']->perguntas_equivalentes)->toBe(['quanto custa?', 'valor'])
        ->and($cards['programas.raiz']->resposta)->toBe('R$ 710,00')
        ->and($cards['programas.raiz']->resposta_detalhada)->toBe('diga o que inclui')
        ->and($cards['programas.raiz']->status)->toBe('validado')
        ->and($cards['programas.raiz']->ativo)->toBeTrue()
        ->and($cards['programas.raiz']->origem)->toBe('base_inicial')
        // string JSON vira lista; card fora do prompt do n8n entra desativado
        ->and($cards['local.endereco']->perguntas_equivalentes)->toBe(['onde fica?'])
        ->and($cards['local.endereco']->ativo)->toBeFalse()
        ->and($cards['agenda.parcelamento']->status)->toBe('pendente')
        // status desconhecido vira pendente; bloqueado entra desativado
        ->and($cards['x.bloqueado']->status)->toBe('pendente')
        ->and($cards['x.bloqueado']->ativo)->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'luna_cards')
        && $request->hasHeader('apikey', 'chave-de-teste')
        && str_contains($request->url(), 'conta=eq.luna'));

    // Rodar de novo não duplica (casa por código).
    Artisan::call('ia:importar-supabase', ['agente' => 'luna']);

    expect(IaCard::query()->where('doctor_id', $this->luna->id)->count())->toBe(4);
});

it('avisa quando faltam as credenciais do Supabase e a URL do n8n', function () {
    config()->set('painel.ia.supabase_url', null);
    config()->set('painel.ia.cards_url', null);

    $codigo = Artisan::call('ia:importar-supabase', ['agente' => 'luna']);

    expect($codigo)->toBe(IaImportarSupabase::FAILURE)
        ->and(Artisan::output())->toContain('SUPABASE_URL');
});

// ---------------------------------------------------------------------------
// Cron por URL (hospedagem compartilhada)
// ---------------------------------------------------------------------------

it('roda o agendador pela URL só com o token certo', function () {
    config()->set('painel.cron_token', 'token-de-teste-com-tamanho-suficiente');

    $this->get('/cron/token-errado-com-tamanho-suficiente')->assertNotFound();
    $this->get('/cron/token-de-teste-com-tamanho-suficiente')->assertOk();
});

it('mantém a rota de cron desligada sem token configurado', function () {
    config()->set('painel.cron_token', null);

    $this->get('/cron/qualquer-coisa-com-tamanho-suficiente')->assertNotFound();
});

// ---------------------------------------------------------------------------
// Segredos derivados da APP_KEY (instalação sem variáveis extras)
// ---------------------------------------------------------------------------

it('deriva o segredo do webhook da APP_KEY quando o .env não define', function () {
    config()->set('painel.ia.webhook_secret', null);

    $derivado = SegredosDoPainel::webhookIa();

    expect($derivado)->toHaveLength(64)
        ->and(SegredosDoPainel::webhookIaVeioDoEnv())->toBeFalse()
        ->and(SegredosDoPainel::cronToken())->toHaveLength(48)
        ->and(SegredosDoPainel::cronToken())->not->toBe(substr($derivado, 0, 48));

    // A assinatura com o segredo derivado abre a API…
    postIa('/api/ia/contexto', ['agente' => 'luna'], 'sha256='.hash_hmac('sha256', '{"agente":"luna"}', $derivado))->assertOk();

    // …e o cron responde na URL derivada.
    $this->get(SegredosDoPainel::cronUrl())->assertOk();
});

it('prefere o segredo do .env quando ele existe', function () {
    config()->set('painel.ia.webhook_secret', 'segredo-do-env');
    config()->set('painel.cron_token', 'token-do-env-com-tamanho-suficiente');

    expect(SegredosDoPainel::webhookIa())->toBe('segredo-do-env')
        ->and(SegredosDoPainel::webhookIaVeioDoEnv())->toBeTrue()
        ->and(SegredosDoPainel::cronToken())->toBe('token-do-env-com-tamanho-suficiente');
});

it('mostra os valores de ligação só para admin na Configuração da IA', function () {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('ia'));

    $admin = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);
    $gestor = User::factory()->create(['role' => UserRole::Gestor, 'doctor_scope' => DoctorScope::Ambos]);

    $this->actingAs($admin)
        ->get(ConfiguracaoDaIa::getUrl())
        ->assertOk()
        ->assertSee('Ligar o painel ao n8n e ao cron')
        ->assertSee(url('/api/ia'))
        ->assertSee(SegredosDoPainel::webhookIa());

    $this->actingAs($gestor)
        ->get(ConfiguracaoDaIa::getUrl())
        ->assertOk()
        ->assertDontSee(SegredosDoPainel::webhookIa());
});

it('busca os cards pelo n8n quando o servidor não tem a chave do Supabase', function () {
    config()->set('painel.ia.supabase_url', null);
    config()->set('painel.ia.supabase_key', null);
    config()->set('painel.ia.cards_url', 'https://n8n.test/webhook/ia-cards');
    config()->set('painel.ia.webhook_secret', 'segredo-teste');

    Http::fake([
        'https://n8n.test/webhook/ia-cards' => Http::response([
            'ok' => true, 'tabela' => 'luna_cards', 'total' => 1,
            'linhas' => [[
                'id' => 'programas.pleno', 'conta' => 'luna', 'modulo' => 'programas', 'categoria' => 'Flor&Ser Pleno',
                'perguntas_equivalentes' => ['o que é o pleno?'], 'resposta_curta' => 'R$ 3.700,00', 'status' => 'validado', 'bloqueado' => false,
            ]],
        ]),
    ]);

    Artisan::call('ia:importar-supabase', ['agente' => 'luna']);

    expect(IaCard::query()->where('codigo', 'programas.pleno')->exists())->toBeTrue()
        ->and(Artisan::output())->toContain('Fonte: n8n');

    Http::assertSent(function ($request) {
        $esperada = 'sha256='.hash_hmac('sha256', $request->body(), 'segredo-teste');

        return $request->url() === 'https://n8n.test/webhook/ia-cards'
            && $request->hasHeader('X-Signature', $esperada)
            && json_decode($request->body(), true) === ['agente' => 'luna', 'tabela' => 'luna_cards'];
    });
});

it('abre as telas com a Luna selecionada, e a Duda só quando pedida', function () {
    $duda = criarMedicoDuda();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    expect(AgenteSelecionado::resolver($admin, null))->toBe($this->luna->id)
        ->and(AgenteSelecionado::resolver($admin, $duda->id))->toBe($duda->id)
        ->and(array_key_first(AgenteSelecionado::options($admin)))->toBe($this->luna->id);
});
