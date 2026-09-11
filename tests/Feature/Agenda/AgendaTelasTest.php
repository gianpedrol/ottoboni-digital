<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Filament\Pages\Agenda;
use App\Filament\Widgets\AgendaHojeWidget;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Agenda\Cenario;

beforeEach(function () {
    config()->set('painel.prototipo', true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->eduardo = Cenario::eduardo();
    $this->vanessa = Cenario::vanessa();

    $this->pacienteEduardo = Cenario::paciente($this->eduardo, 'Paciente Do Eduardo');
    $this->pacienteVanessa = Cenario::paciente($this->vanessa, 'Paciente Da Vanessa', 777002);

    $this->agEduardo = Cenario::agendamento($this->eduardo, $this->pacienteEduardo, Cenario::tercaDestaSemana(9));
    $this->agVanessa = Cenario::agendamento($this->vanessa, $this->pacienteVanessa, Cenario::tercaDestaSemana(9));
});

it('admin abre a agenda na visão semana e vê os dois médicos', function () {
    $admin = Cenario::usuario(UserRole::Admin);

    $this->actingAs($admin)
        ->get('/painel/agenda')
        ->assertOk()
        ->assertSee('Demonstração · dados fictícios')
        ->assertSee('Paciente Do Eduardo')
        ->assertSee('Paciente Da Vanessa');
});

it('admin abre a visão dia (recepção)', function () {
    $admin = Cenario::usuario(UserRole::Admin);
    $data = Cenario::tercaDestaSemana()->format('Y-m-d');

    $this->actingAs($admin)
        ->get("/painel/agenda?visao=dia&data={$data}")
        ->assertOk()
        ->assertSee('Paciente Do Eduardo')
        ->assertSee('Confirmar');
});

it('admin abre a lista, o cadastro e a edição de agendamentos', function () {
    $admin = Cenario::usuario(UserRole::Admin);

    $this->actingAs($admin)->get('/painel/agendamentos')->assertOk()->assertSee('Paciente Do Eduardo');
    $this->actingAs($admin)->get('/painel/agendamentos/create')->assertOk();
    $this->actingAs($admin)->get("/painel/agendamentos/{$this->agEduardo->id}/edit")->assertOk();
});

it('recepção do Dr. Eduardo não vê a agenda da Dra. Vanessa', function () {
    $recepcao = Cenario::usuario(UserRole::Recepcao, DoctorScope::Eduardo);

    // Mesmo pedindo a Vanessa pela URL, recebe só o que pode ver.
    $this->actingAs($recepcao)
        ->get('/painel/agenda?medico=luna')
        ->assertOk()
        ->assertSee('Paciente Do Eduardo')
        ->assertDontSee('Paciente Da Vanessa');

    $this->actingAs($recepcao)
        ->get('/painel/agendamentos')
        ->assertOk()
        ->assertSee('Paciente Do Eduardo')
        ->assertDontSee('Paciente Da Vanessa');

    $this->actingAs($recepcao)
        ->get("/painel/agendamentos/{$this->agVanessa->id}/edit")
        ->assertNotFound();
});

it('recepção não abre nem altera agendamento do outro médico pelos modais', function () {
    $recepcao = Cenario::usuario(UserRole::Recepcao, DoctorScope::Eduardo);
    $this->actingAs($recepcao);

    // O registro é buscado já no escopo do usuário: para o id da Vanessa a
    // ação nem existe (fica oculta) e o modal não abre.
    Livewire::test(Agenda::class)
        ->set('medico', 'luna')
        ->assertDontSee('Paciente Da Vanessa')
        ->assertActionHidden('cancelar', arguments: ['id' => $this->agVanessa->id])
        ->assertActionHidden('detalhe', arguments: ['id' => $this->agVanessa->id])
        ->assertActionVisible('cancelar', arguments: ['id' => $this->agEduardo->id])
        ->call('mountAction', 'cancelar', ['id' => $this->agVanessa->id])
        ->call('callMountedAction');

    expect($this->agVanessa->refresh()->status->value)->toBe('agendado');
});

it('widget da agenda de hoje respeita o escopo da recepção', function () {
    $hoje = CarbonImmutable::now(Appointment::fuso())->setTime(11, 0);
    Cenario::agendamento($this->eduardo, $this->pacienteEduardo, $hoje);
    Cenario::agendamento($this->vanessa, $this->pacienteVanessa, $hoje);

    $this->actingAs(Cenario::usuario(UserRole::Recepcao, DoctorScope::Eduardo));

    Livewire::test(AgendaHojeWidget::class)
        ->assertSee('Paciente Do Eduardo')
        ->assertDontSee('Paciente Da Vanessa');
});
