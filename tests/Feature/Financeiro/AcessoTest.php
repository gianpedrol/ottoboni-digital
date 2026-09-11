<?php

use App\Enums\DoctorScope;
use App\Enums\UserRole;
use App\Filament\Resources\BankTransactions\Widgets\BankAccountsStats;
use App\Filament\Resources\Payables\Widgets\PayablesStats;
use App\Filament\Resources\Receivables\Widgets\ReceivablesStats;
use App\Filament\Widgets\Financeiro\FluxoPrevistoRealizadoChart;
use App\Filament\Widgets\Financeiro\InadimplenciaChart;
use App\Filament\Widgets\Financeiro\ReceitaPorMedicoChart;
use App\Filament\Widgets\Financeiro\ReceitaPorServicoChart;
use App\Filament\Widgets\Financeiro\RepassesChart;
use App\Filament\Widgets\Financeiro\ResumoFinanceiroStats;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\FinanceiroDemoSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    $this->admin = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin-fin@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => UserRole::Admin,
        'doctor_scope' => DoctorScope::Ambos,
    ]);

    $this->gestor = User::query()->create([
        'name' => 'Gestor',
        'email' => 'gestor-fin@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => UserRole::Gestor,
        'doctor_scope' => DoctorScope::Ambos,
    ]);

    $this->recepcao = User::query()->create([
        'name' => 'Recepção',
        'email' => 'recepcao-fin@ottoboni.local',
        'password' => 'senha-de-teste',
        'role' => UserRole::Recepcao,
        'doctor_scope' => DoctorScope::Ambos,
    ]);

    $this->seed(FinanceiroDemoSeeder::class);

    $receivable = Receivable::query()->firstOrFail();
    $service = Service::query()->firstOrFail();
    $payable = Payable::query()->firstOrFail();

    $this->urls = [
        '/painel/tabela-de-precos',
        '/painel/tabela-de-precos/create',
        "/painel/tabela-de-precos/{$service->id}/edit",
        '/painel/contas-a-receber',
        '/painel/contas-a-receber/create',
        "/painel/contas-a-receber/{$receivable->id}",
        "/painel/contas-a-receber/{$receivable->id}/edit",
        '/painel/contas-a-pagar',
        '/painel/contas-a-pagar/create',
        "/painel/contas-a-pagar/{$payable->id}/edit",
        '/painel/extrato-bancario',
        '/painel/fluxo-de-caixa',
    ];
});

it('admin abre todas as telas do financeiro, com o selo de demonstração', function () {
    foreach ($this->urls as $url) {
        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertSee('Demonstração · dados fictícios');
    }
});

it('gestor também acessa o financeiro', function () {
    $this->actingAs($this->gestor)->get('/painel/contas-a-receber')->assertOk();
    $this->actingAs($this->gestor)->get('/painel/fluxo-de-caixa')->assertOk();
});

it('recepção não acessa nenhuma tela do financeiro', function () {
    foreach ($this->urls as $url) {
        $this->actingAs($this->recepcao)->get($url)->assertForbidden();
    }
});

it('widgets de cabeçalho e gráficos renderizam com os dados de demonstração', function () {
    $this->actingAs($this->admin);

    foreach ([ReceivablesStats::class, PayablesStats::class, BankAccountsStats::class] as $widget) {
        Livewire::test($widget)->assertOk();
    }

    $graficos = [
        ResumoFinanceiroStats::class,
        FluxoPrevistoRealizadoChart::class,
        ReceitaPorMedicoChart::class,
        ReceitaPorServicoChart::class,
        RepassesChart::class,
        InadimplenciaChart::class,
    ];

    foreach ($graficos as $widget) {
        Livewire::test($widget, ['pageFilters' => ['periodo' => '6m', 'medico' => null]])->assertOk();
    }
});

it('widgets do financeiro não aparecem para a recepção', function () {
    $this->actingAs($this->recepcao);

    expect(ReceivablesStats::canView())->toBeFalse()
        ->and(FluxoPrevistoRealizadoChart::canView())->toBeFalse();
});
