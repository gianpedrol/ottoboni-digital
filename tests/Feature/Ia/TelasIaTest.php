<?php

use App\Enums\DoctorScope;
use App\Enums\IaApprovalStatus;
use App\Enums\IaGrauEdicao;
use App\Enums\IaIntent;
use App\Enums\UserRole;
use App\Filament\Ia\Pages\Acuracia;
use App\Filament\Ia\Pages\ConfiguracaoDaIa;
use App\Filament\Ia\Pages\FilaDeAprovacao;
use App\Filament\Ia\Pages\PromptsERegras;
use App\Filament\Ia\Resources\IaApprovals\IaApprovalResource;
use App\Filament\Ia\Resources\IaCards\IaCardResource;
use App\Filament\Ia\Resources\IaExamples\IaExampleResource;
use App\Filament\Ia\Resources\IaTriggers\IaTriggerResource;
use App\Models\IaGateSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Filament::setCurrentPanel(Filament::getPanel('ia'));

    $this->luna = criarMedicoLuna();

    $this->actingAs(User::query()->create([
        'name' => 'Pietra',
        'email' => uniqid().'@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => UserRole::Admin,
        'doctor_scope' => DoctorScope::Ambos,
    ]));
});

it('abre todas as telas do painel da IA', function () {
    itemPendente($this->luna);
    revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::Leve);

    $urls = [
        FilaDeAprovacao::getUrl(),
        Acuracia::getUrl(),
        ConfiguracaoDaIa::getUrl(),
        PromptsERegras::getUrl(),
        IaCardResource::getUrl('index'),
        IaExampleResource::getUrl('index'),
        IaTriggerResource::getUrl('index'),
        IaApprovalResource::getUrl('index'),
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
});

it('mostra a fila zerada quando não há nada pendente', function () {
    Livewire::test(FilaDeAprovacao::class)
        ->assertSee('Fila zerada')
        ->assertSet('itemId', null);
});

it('traz de volta os itens pulados', function () {
    $item = itemPendente($this->luna);

    Livewire::test(FilaDeAprovacao::class)
        ->call('pular')
        ->assertSet('itemId', null)
        ->assertSee('Rever os pulados')
        ->call('reverPulados')
        ->assertSet('itemId', $item->id)
        ->assertSee('quanto custa a consulta?');
});

it('fecha o modal e passa para o próximo depois de rejeitar', function () {
    $primeiro = itemPendente($this->luna);
    $primeiro->forceFill(['created_at' => now()->subHour()])->save();
    $segundo = itemPendente($this->luna);

    Livewire::test(FilaDeAprovacao::class)
        ->assertSet('itemId', $primeiro->id)
        ->set('motivoRejeicao', 'comentário de aniversário, não precisava de resposta')
        ->call('rejeitar')
        ->assertDispatched('close-modal', id: 'rejeitar')
        ->assertSet('itemId', $segundo->id)
        ->assertSet('revisadosNaSessao', 1);

    expect($primeiro->refresh()->status)->toBe(IaApprovalStatus::Rejeitado);
});

it('conta a rejeição uma vez só no gráfico de onde ela erra', function () {
    revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::Refeita);
    revisaoFechada($this->luna, IaIntent::Consulta, IaGrauEdicao::Refeita, IaApprovalStatus::Rejeitado);

    $dist = Livewire::test(Acuracia::class)->instance()->distribuicao();

    expect($dist['refeita'])->toBe(1)
        ->and($dist['rejeitadas'])->toBe(1);
});

it('mostra as acurácias em porcentagem e grava de 0 a 1', function () {
    IaGateSetting::paraAgente($this->luna->id)->update([
        'limiar_acuracia' => 0.9,
        'kill_switch_acuracia' => 0.75,
    ]);

    Livewire::test(ConfiguracaoDaIa::class)
        ->assertSet('data.limiar_acuracia', 90)
        ->assertSet('data.kill_switch_acuracia', 75)
        ->set('data.limiar_acuracia', 85)
        ->call('salvar')
        ->assertHasNoErrors();

    expect(IaGateSetting::paraAgente($this->luna->id)->limiar_acuracia)->toBe(0.85);
});

it('não deixa o freio de mão acima da acurácia para liberar', function () {
    Livewire::test(ConfiguracaoDaIa::class)
        ->set('data.limiar_acuracia', 80)
        ->set('data.kill_switch_acuracia', 90)
        ->call('salvar')
        ->assertHasErrors(['data.kill_switch_acuracia' => 'lte']);
});

it('mostra um bloco do prompt por aba', function () {
    Livewire::test(PromptsERegras::class)
        ->assertSee('Quem a agente é')
        ->assertSee('Mensagem ao passar para a equipe')
        ->assertDontSee('(PERSONA)');
});
