<?php

namespace App\Filament\Ia\Pages;

use App\Enums\IaIntent;
use App\Models\IaApproval;
use App\Models\User;
use App\Services\Ia\AprovadorDeResposta;
use App\Services\Ia\MontadorDeContexto;
use App\Support\AgenteSelecionado;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use UnitEnum;

/**
 * A tela principal: revisar uma interação por vez.
 *
 * Um item de cada vez, e não 20 formulários na mesma página — com fila cheia
 * o que importa é a velocidade de decidir, e por isso existem os atalhos de
 * teclado (A aprova, R rejeita, S pula, E edita o direct).
 *
 * Nada sai para o Instagram por esta tela diretamente: aprovar grava a
 * decisão e o envio real é feito pelo n8n, que devolve o resultado no
 * callback. O painel não tem, e não deve ter, o token do Instagram.
 */
class FilaDeAprovacao extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Revisão';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Fila de aprovação';

    protected static ?string $navigationLabel = 'Fila de aprovação';

    protected string $view = 'filament.ia.pages.fila-de-aprovacao';

    public ?int $doctorId = null;

    public ?int $itemId = null;

    /** Texto da resposta pública, editável. */
    public ?string $comentario = null;

    /** Texto do direct, editável. */
    public ?string $dm = null;

    public bool $aceite = false;

    public ?string $observacao = null;

    /** Códigos dos materiais que vão junto (pré-marcados com a escolha da agente). */
    public array $materiais = [];

    public ?string $motivoRejeicao = null;

    /** @var array<int, int> Itens que o revisor pulou nesta sessão. */
    public array $pulados = [];

    /** Aprovados e rejeitados desde que a tela abriu — só para dar ritmo. */
    public int $revisadosNaSessao = 0;

    public static function getNavigationBadge(): ?string
    {
        $total = self::baseQuery()->pendentes()->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $naoSei = self::baseQuery()
            ->pendentes()
            ->where('intent', IaIntent::NaoSei)
            ->exists();

        return $naoSei ? 'danger' : 'warning';
    }

    public function mount(): void
    {
        $this->doctorId = AgenteSelecionado::resolver($this->usuario(), null);
        $this->carregarProximo();
    }

    public function updatedDoctorId(): void
    {
        $this->doctorId = AgenteSelecionado::resolver($this->usuario(), $this->doctorId);
        $this->pulados = [];
        $this->carregarProximo();
    }

    public function carregarProximo(?int $forcarId = null): void
    {
        $this->resetarCampos();

        $query = $this->filaQuery();

        $item = $forcarId !== null
            ? (clone $query)->whereKey($forcarId)->first()
            : $this->ordenar($query->whereNotIn('id', $this->pulados))->first();

        $this->itemId = $item?->id;
        $this->comentario = $item?->rascunho_comentario;
        $this->dm = $item?->rascunho_dm;
        $this->materiais = $item !== null ? ($item->materiais ?? []) : [];
    }

    public function abrir(int $id): void
    {
        $this->carregarProximo($id);
    }

    public function pular(): void
    {
        if ($this->itemId !== null) {
            $this->pulados[] = $this->itemId;
        }

        $this->carregarProximo();
    }

    public function reverPulados(): void
    {
        $this->pulados = [];
        $this->carregarProximo();
    }

    public function aprovar(): void
    {
        $item = $this->item();

        if ($item === null) {
            return;
        }

        try {
            app(AprovadorDeResposta::class)->aprovar(
                item: $item,
                comentario: $this->comentario,
                dm: $this->dm,
                autor: $this->usuario(),
                aceite: $this->aceite,
                observacao: $this->observacao,
                materiais: array_values(array_map('strval', $this->materiais)),
            );
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Não foi possível aprovar')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Aprovado')
            ->body('A resposta foi para a fila de envio. O status muda para "enviado" quando o n8n confirmar.')
            ->send();

        $this->revisadosNaSessao++;
        $this->carregarProximo();
    }

    public function rejeitar(): void
    {
        $item = $this->item();

        if ($item === null) {
            return;
        }

        if (blank($this->motivoRejeicao)) {
            Notification::make()
                ->warning()
                ->title('Diga o motivo')
                ->body('O motivo é o que ensina a agente a não repetir o erro.')
                ->send();

            return;
        }

        try {
            app(AprovadorDeResposta::class)->rejeitar($item, $this->usuario(), (string) $this->motivoRejeicao);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Não foi possível rejeitar')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Rejeitado')
            ->body('Nada foi enviado. O item conta como erro na acurácia.')
            ->send();

        $this->revisadosNaSessao++;
        $this->dispatch('close-modal', id: 'rejeitar');
        $this->carregarProximo();
    }

    public function item(): ?IaApproval
    {
        if ($this->itemId === null) {
            return null;
        }

        return IaApproval::query()->with(['doctor', 'promptVersion'])->find($this->itemId);
    }

    /** @return Collection<int, IaApproval> */
    public function fila(): Collection
    {
        return $this->ordenar($this->filaQuery())
            ->limit(50)
            ->get();
    }

    /**
     * @return array{pendentes: int, nao_sei: int, atrasados: int}
     */
    public function resumo(): array
    {
        $itens = $this->filaQuery()->get(['intent', 'expira_em']);

        return [
            'pendentes' => $itens->count(),
            'nao_sei' => $itens->where('intent', IaIntent::NaoSei)->count(),
            'atrasados' => $itens->filter(
                fn (IaApproval $i): bool => $i->expira_em !== null && $i->expira_em->isPast()
            )->count(),
        ];
    }

    /** @return array<int, string> */
    public function agentes(): array
    {
        return AgenteSelecionado::options($this->usuario());
    }

    /** @return Builder<IaApproval> */
    private function filaQuery(): Builder
    {
        return IaApproval::query()
            ->pendentes()
            ->whereIn('doctor_id', AgenteSelecionado::permitidos($this->usuario()))
            ->when($this->doctorId !== null, fn (Builder $q) => $q->where('doctor_id', $this->doctorId));
    }

    /**
     * Ordem da fila: o que a agente não soube responder primeiro, depois o que
     * passou do prazo, depois por ordem de chegada. Comentário no Instagram
     * esfria rápido — deixar o mais antigo para o fim seria perder o lead.
     *
     * A lista lateral usa a mesma ordem, então "o próximo" é sempre o de cima.
     *
     * @param  Builder<IaApproval>  $query
     * @return Builder<IaApproval>
     */
    private function ordenar(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN intent = ? THEN 0 ELSE 1 END', [IaIntent::NaoSei->value])
            ->orderByRaw('CASE WHEN expira_em IS NOT NULL AND expira_em < ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('created_at');
    }

    /**
     * Usada pelo badge do menu (contexto estático, sem instância da página).
     *
     * @return Builder<IaApproval>
     */
    private static function baseQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        return IaApproval::query()->whereIn('doctor_id', AgenteSelecionado::permitidos($user));
    }

    private function resetarCampos(): void
    {
        $this->comentario = null;
        $this->dm = null;
        $this->aceite = false;
        $this->observacao = null;
        $this->motivoRejeicao = null;
        $this->materiais = [];
    }

    /**
     * Materiais ativos da agente do item, para o revisor marcar o que vai junto.
     *
     * @return array<int, array{codigo: string, nome: string, tipo: string, url: ?string, quando_usar: ?string}>
     */
    public function materiaisDisponiveis(): array
    {
        $item = $this->item();

        if ($item === null) {
            return [];
        }

        return app(MontadorDeContexto::class)->materiais($item->doctor_id);
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
