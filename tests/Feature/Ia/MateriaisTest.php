<?php

use App\Enums\DoctorScope;
use App\Enums\IaIntent;
use App\Enums\UserRole;
use App\Filament\Ia\Pages\FilaDeAprovacao;
use App\Filament\Ia\Resources\IaMaterials\IaMaterialResource;
use App\Jobs\EnviarRespostaAprovadaJob;
use App\Models\Doctor;
use App\Models\IaApproval;
use App\Models\IaCard;
use App\Models\IaExample;
use App\Models\IaMaterial;
use App\Models\User;
use App\Services\Ia\AprovadorDeResposta;
use App\Services\Ia\IaWebhookClient;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('painel.ia.webhook_secret', 'segredo-teste');
    Storage::fake('public');
    $this->luna = criarMedicoLuna();
});

function materialImagem(Doctor $doctor, string $codigo = 'raiz_foto'): IaMaterial
{
    $arquivo = UploadedFile::fake()->image('raiz.png', 600, 400);
    $caminho = $arquivo->storeAs('ia-materiais', "{$codigo}.png", 'public');

    return IaMaterial::query()->create([
        'doctor_id' => $doctor->id,
        'codigo' => $codigo,
        'nome' => 'Foto do Flor&Ser Raiz',
        'tipo' => 'imagem',
        'arquivo' => $caminho,
        'quando_usar' => 'quando perguntarem do Raiz',
    ]);
}

it('entrega os materiais no contexto, no prompt e na linha do card', function () {
    $foto = materialImagem($this->luna);
    IaMaterial::query()->create(['doctor_id' => $this->luna->id, 'codigo' => 'ebook', 'nome' => 'E-book Clean Beauty', 'tipo' => 'documento', 'arquivo' => null]);
    IaMaterial::query()->create(['doctor_id' => $this->luna->id, 'codigo' => 'video_dra', 'nome' => 'Vídeo da Dra.', 'tipo' => 'video', 'url' => 'https://www.instagram.com/reel/x/']);
    IaMaterial::query()->create(['doctor_id' => $this->luna->id, 'codigo' => 'antigo', 'nome' => 'Desativado', 'tipo' => 'link', 'url' => 'https://x.test', 'ativo' => false]);

    IaCard::query()->create([
        'doctor_id' => $this->luna->id, 'codigo' => 'programas.raiz', 'categoria' => 'Flor&Ser Raiz',
        'pergunta' => 'quanto custa o raiz?', 'resposta' => 'R$ 710,00', 'status' => 'validado', 'materiais' => ['raiz_foto', 'nao_existe'],
    ]);

    $r = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    $codigos = collect($r->json('materiais'))->pluck('codigo')->all();

    // documento sem arquivo e material desativado ficam de fora; imagem tem URL pública com token
    expect($codigos)->toBe(['raiz_foto', 'video_dra'])
        ->and($r->json('materiais.0.url'))->toContain('/materiais/'.$foto->token.'/raiz_foto.png')
        ->and($r->json('system_prompt'))->toContain('### MATERIAIS QUE VOCÊ PODE ENVIAR')
        ->and($r->json('system_prompt'))->toContain('[raiz_foto] Foto do Flor&Ser Raiz (imagem, vai como anexo) — quando usar: quando perguntarem do Raiz')
        ->and($r->json('system_prompt'))->toContain('[video_dra] Vídeo da Dra. (vai como link na mensagem)')
        ->and($r->json('cards_texto'))->toContain("Resposta oficial: R$ 710,00\nMateriais para enviar junto: [raiz_foto] Foto do Flor&Ser Raiz\n");
});

it('não põe o bloco de materiais no prompt quando não há nenhum', function () {
    $r = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    expect($r->json('materiais'))->toBe([])
        ->and($r->json('system_prompt'))->not->toContain('MATERIAIS QUE VOCÊ PODE ENVIAR');
});

it('serve o arquivo do material pela URL pública e nega token errado', function () {
    $foto = materialImagem($this->luna);

    $this->get($foto->urlPublica())->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get('/materiais/'.str_repeat('x', 40).'/raiz_foto.png')->assertNotFound();
    $this->get('/materiais/'.$foto->token.'/outro.png')->assertNotFound();

    $foto->update(['ativo' => false]);
    $this->get($foto->urlPublica())->assertNotFound();
});

it('guarda o que a agente escolheu e manda ao n8n o que o humano deixou, já com URL', function () {
    Queue::fake();
    $foto = materialImagem($this->luna);
    IaMaterial::query()->create(['doctor_id' => $this->luna->id, 'codigo' => 'video_dra', 'nome' => 'Vídeo da Dra.', 'tipo' => 'video', 'url' => 'https://www.instagram.com/reel/x/']);

    $r = postIa('/api/ia/rascunho', [
        'agente' => 'luna', 'canal' => 'direct', 'intent' => 'consulta',
        'ig_id' => '123', 'mensagem_texto' => 'quanto custa o raiz?', 'rascunho_dm' => 'O investimento é R$ 710,00.',
        'materiais' => ['raiz_foto', 'video_dra', 'raiz_foto', 'inventado'],
    ])->assertOk();

    $item = IaApproval::query()->findOrFail($r->json('approval_id'));

    expect($item->materiais)->toBe(['raiz_foto', 'video_dra', 'inventado']);

    $revisor = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    // o humano tira o vídeo
    app(AprovadorDeResposta::class)->aprovar($item, null, null, $revisor, true, null, true, ['raiz_foto', 'inventado']);

    expect($item->refresh()->final_materiais)->toBe(['raiz_foto', 'inventado']);

    // o job resolve só o que existe e está ativo, com a URL pronta
    config()->set('painel.ia.envio_url', 'https://n8n.test/webhook/ia-envio-aprovado');
    Http::fake(['https://n8n.test/*' => Http::response(['ok' => true])]);

    (new EnviarRespostaAprovadaJob($item->id))->handle(app(IaWebhookClient::class));

    Http::assertSent(function ($request) use ($foto) {
        $corpo = json_decode($request->body(), true);

        return $corpo['materiais'] === [[
            'codigo' => 'raiz_foto', 'nome' => 'Foto do Flor&Ser Raiz', 'tipo' => 'imagem', 'url' => $foto->urlPublica(),
        ]] && $corpo['rascunho_dm'] === 'O investimento é R$ 710,00.';
    });
});

it('aprovar sem mexer nos materiais manda os que a agente escolheu', function () {
    Queue::fake();
    materialImagem($this->luna);

    $item = itemPendente($this->luna, ['materiais' => ['raiz_foto']]);
    $revisor = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    app(AprovadorDeResposta::class)->aprovar($item, null, null, $revisor, true);

    expect($item->refresh()->final_materiais)->toBe(['raiz_foto']);
});

it('normaliza o código do material a partir do nome', function () {
    $m = IaMaterial::query()->create(['doctor_id' => $this->luna->id, 'nome' => 'Foto do Flor&Ser Pleno!', 'tipo' => 'link', 'url' => 'https://x.test', 'codigo' => '']);

    expect($m->codigo)->toBe('foto_do_flor_ser_pleno')
        ->and($m->token)->toHaveLength(40);
});

it('abre a tela de materiais e mostra os materiais na fila', function () {
    Filament::setCurrentPanel(Filament::getPanel('ia'));
    materialImagem($this->luna);
    itemPendente($this->luna, ['materiais' => ['raiz_foto']]);

    $admin = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    $this->actingAs($admin)->get(IaMaterialResource::getUrl('index'))->assertOk()->assertSee('Foto do Flor&Ser Raiz');
    $this->actingAs($admin)->get(FilaDeAprovacao::getUrl())->assertOk()
        ->assertSee('Vai junto com o direct')
        ->assertSee('Foto do Flor&Ser Raiz');
});

it('a escolha de materiais do humano vira exemplo e entra no prompt como treino', function () {
    Queue::fake();
    materialImagem($this->luna);
    IaMaterial::query()->create(['doctor_id' => $this->luna->id, 'codigo' => 'pleno_pdf', 'nome' => 'PDF do Flor&Ser Pleno', 'tipo' => 'link', 'url' => 'https://x.test/pleno.pdf']);

    // a agente escolheu o material errado; o humano troca sem mexer no texto
    $item = itemPendente($this->luna, ['materiais' => ['pleno_pdf']]);
    $revisor = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    app(AprovadorDeResposta::class)->aprovar($item, null, null, $revisor, true, null, true, ['raiz_foto']);

    $exemplo = IaExample::query()->where('approval_id', $item->id)->sole();

    expect($exemplo->materiais)->toBe(['raiz_foto'])
        ->and($exemplo->resposta)->toBe($item->rascunho_dm);

    $r = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    expect($r->json('exemplos.0.materiais'))->toBe(['raiz_foto'])
        ->and($r->json('system_prompt'))->toContain('### EXEMPLOS APROVADOS PELA EQUIPE')
        ->and($r->json('system_prompt'))->toContain('Materiais que foram junto: [raiz_foto]')
        ->and($r->json('system_prompt'))->toContain('Resposta aprovada pela equipe: '.$item->rascunho_dm);
});

it('aprovar sem tocar em nada não cria exemplo', function () {
    Queue::fake();
    materialImagem($this->luna);

    $item = itemPendente($this->luna, ['materiais' => ['raiz_foto']]);
    $revisor = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    app(AprovadorDeResposta::class)->aprovar($item, null, null, $revisor, true);

    expect(IaExample::query()->where('approval_id', $item->id)->exists())->toBeFalse();
});

it('um "não sei" respondido pelo humano vira card já com o material', function () {
    Queue::fake();
    materialImagem($this->luna);

    $item = itemPendente($this->luna, ['intent' => IaIntent::NaoSei, 'rascunho_dm' => null, 'materiais' => []]);
    $revisor = User::factory()->create(['role' => UserRole::Admin, 'doctor_scope' => DoctorScope::Ambos]);

    app(AprovadorDeResposta::class)->aprovar($item, null, 'O Raiz custa R$ 710,00 e inclui a consulta.', $revisor, true, null, true, ['raiz_foto']);

    $card = IaCard::query()->where('approval_id', $item->id)->sole();

    expect($card->materiais)->toBe(['raiz_foto'])
        ->and($card->status)->toBe('validado');

    $r = postIa('/api/ia/contexto', ['agente' => 'luna'])->assertOk();

    expect($r->json('cards_texto'))->toContain('Materiais para enviar junto: [raiz_foto] Foto do Flor&Ser Raiz');
});
