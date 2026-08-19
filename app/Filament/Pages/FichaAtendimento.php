<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\FollowupRun;
use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\PipelineData;
use App\Services\Kommo\KommoException;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class FichaAtendimento extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'atendimentos/{leadId}';

    protected string $view = 'filament.pages.ficha-atendimento';

    public int $leadId;

    /**
     * Dados do lead em tipos primitivos — propriedade pública do Livewire
     * não aguenta DTO com CarbonImmutable entre requests.
     *
     * @var array<string, mixed>|null
     */
    public ?array $lead = null;

    public ?string $medico = null;

    public ?string $etapa = null;

    public ?string $responsavel = null;

    public bool $notasCarregadas = false;

    /** @var array<int, array<string, mixed>> */
    public array $notas = [];

    /** @var array<int, array<string, mixed>> */
    public array $tarefas = [];

    /** @var array<int, array<string, mixed>> */
    public array $contatos = [];

    /** @var array<int, array<string, mixed>> */
    public array $followups = [];

    public ?string $erro = null;

    public function mount(int $leadId): void
    {
        $this->leadId = $leadId;

        $repo = app(LeadRepository::class);

        try {
            $lead = $repo->find($leadId);
        } catch (KommoException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        if ($lead === null) {
            $this->erro = 'Lead não encontrado no Kommo.';

            return;
        }

        // O escopo vale também por URL direta: recepção de um médico não
        // abre ficha de lead do outro.
        if (! in_array($lead->pipelineId, $this->usuario()->allowedPipelineIds(), true)) {
            abort(403);
        }

        AuditLog::registrar('abriu_ficha', ['lead_id' => $leadId]);

        $tzLocal = config('painel.timezone');

        $this->lead = [
            'id' => $lead->id,
            'name' => $lead->name,
            'instagram' => $lead->instagramHandle(),
            'origem' => $lead->origem,
            'temperatura' => $lead->temperatura,
            'procedimento' => $lead->procedimento,
            'urgencia' => $lead->urgencia,
            'score' => $lead->score,
            'gerado_pela_agente' => $lead->geradoPelaAgente(),
            'loss_reason' => $lead->lossReason,
            'kommo_url' => $lead->kommoUrl(),
            'criado_em' => $lead->createdAt->setTimezone($tzLocal)->format('d/m/Y H:i'),
        ];

        $doctor = Doctor::byPipeline($lead->pipelineId);
        $this->medico = $doctor?->nome;

        $this->etapa = $repo->pipelines()
            ->first(fn (PipelineData $p): bool => $p->id === $lead->pipelineId)
            ?->statusName($lead->statusId);

        $this->responsavel = $repo->users()
            ->first(fn ($u): bool => $u->id === $lead->responsibleUserId)
            ?->name;

        $this->contatos = $repo->contacts($lead->contactIds)
            ->map(fn ($c): array => [
                'nome' => $c->name,
                'telefone' => $c->phone,
                'email' => $c->email,
            ])
            ->all();

        $tz = config('painel.timezone');

        $this->tarefas = $repo->openTasks($leadId)
            ->map(fn ($t): array => [
                'texto' => $t->text,
                'prazo' => $t->completeTill?->setTimezone($tz)->format('d/m/Y H:i'),
            ])
            ->all();

        $this->followups = FollowupRun::query()
            ->where('lead_id_kommo', $leadId)
            ->with('step', 'plan')
            ->orderByDesc('agendado_para')
            ->get()
            ->map(fn (FollowupRun $run): array => [
                'plano' => $run->plan?->nome,
                'status' => $run->status->getLabel(),
                'agendado_para' => $run->agendado_para->setTimezone($tz)->format('d/m/Y H:i'),
                'canal' => $run->canal_usado?->getLabel(),
            ])
            ->all();
    }

    /**
     * Notas carregadas sob demanda (aba Histórico), não junto da ficha.
     */
    public function carregarNotas(): void
    {
        $tz = config('painel.timezone');

        $this->notas = app(LeadRepository::class)->notes($this->leadId)
            ->map(fn ($n): array => [
                'tipo' => $n->noteType,
                'texto' => $n->text,
                'humana' => $n->humana(),
                'quando' => $n->createdAt->setTimezone($tz)->format('d/m/Y H:i'),
            ])
            ->all();

        $this->notasCarregadas = true;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->lead['name'] ?? 'Atendimento';
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
