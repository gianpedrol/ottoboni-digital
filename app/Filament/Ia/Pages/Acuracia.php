<?php

namespace App\Filament\Ia\Pages;

use App\Enums\IaApprovalStatus;
use App\Enums\IaGateModo;
use App\Models\IaApproval;
use App\Models\IaGateSetting;
use App\Models\User;
use App\Services\Ia\CalculadoraDeAcuracia;
use App\Services\Ia\PortaoDeAprovacao;
use App\Support\AgenteSelecionado;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * A nota da agente e o que ela libera.
 *
 * O número honesto para mostrar à cliente é a taxa "aprovado sem editar":
 * de cada 100 respostas, quantas a equipe mandou sem tocar. A acurácia é a
 * média ponderada (sem edição 1,0 · ajuste leve 0,8 · ajuste grande 0,4 ·
 * refeita ou rejeitada 0) e é ela que o portão usa.
 */
class Acuracia extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Treinamento';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Acurácia';

    protected string $view = 'filament.ia.pages.acuracia';

    public ?int $doctorId = null;

    public function mount(): void
    {
        $this->doctorId = AgenteSelecionado::resolver($this->usuario(), null);
    }

    public function updatedDoctorId(): void
    {
        $this->doctorId = AgenteSelecionado::resolver($this->usuario(), $this->doctorId);
    }

    /** @return array{amostras: int, acuracia: ?float, taxa_sem_edicao: ?float, rejeitadas: int} */
    public function geral(): array
    {
        return app(CalculadoraDeAcuracia::class)->geral((int) $this->doctorId);
    }

    /** @return array<int, array<string, mixed>> */
    public function porIntent(): array
    {
        return app(CalculadoraDeAcuracia::class)->tabela(
            (int) $this->doctorId,
            app(PortaoDeAprovacao::class),
        );
    }

    /** @return array<int, array{pergunta: string, vezes: int}> */
    public function lacunas(): array
    {
        return app(CalculadoraDeAcuracia::class)->lacunas((int) $this->doctorId);
    }

    public function config(): IaGateSetting
    {
        return IaGateSetting::paraAgente((int) $this->doctorId);
    }

    /**
     * Contagem por grau de edição na janela — mostra ONDE a agente erra.
     *
     * @return array<string, int>
     */
    public function distribuicao(): array
    {
        $cfg = $this->config();

        $itens = IaApproval::query()
            ->where('doctor_id', $this->doctorId)
            ->avaliaveis()
            ->orderByDesc('revisado_em')
            ->limit($cfg->janela)
            ->get(['grau_edicao', 'status']);

        return [
            'sem_edicao' => $itens->filter(fn (IaApproval $i): bool => $i->grau_edicao?->value === 'sem_edicao')->count(),
            'leve' => $itens->filter(fn (IaApproval $i): bool => $i->grau_edicao?->value === 'leve')->count(),
            'media' => $itens->filter(fn (IaApproval $i): bool => $i->grau_edicao?->value === 'media')->count(),
            // Rejeitar também grava grau "refeita" (nota 0); aqui a rejeição
            // conta só na linha dela, senão aparece duas vezes no gráfico.
            'refeita' => $itens->filter(
                fn (IaApproval $i): bool => $i->grau_edicao?->value === 'refeita' && $i->status !== IaApprovalStatus::Rejeitado
            )->count(),
            'rejeitadas' => $itens->where('status', IaApprovalStatus::Rejeitado)->count(),
        ];
    }

    public function modoAtual(): IaGateModo
    {
        return $this->config()->modo;
    }

    /** @return array<int, string> */
    public function agentes(): array
    {
        return AgenteSelecionado::options($this->usuario());
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
