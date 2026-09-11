<?php

namespace App\Filament\Ia\Pages;

use App\Models\IaGuardrail;
use App\Models\IaPromptVersion;
use App\Models\User;
use App\Services\Ia\PublicadorDePrompt;
use App\Support\AgenteSelecionado;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use UnitEnum;

/**
 * Onde o humano edita as instruções da agente — com responsabilidade total.
 *
 * O que é editável: os blocos do prompt (persona, escrita, base, modos...).
 * O que NÃO é: as regras inegociáveis, que a tela mostra em cinza e o
 * MontadorDeContexto injeta sempre no fim do system prompt, prevalecendo
 * sobre qualquer coisa que se escreva aqui.
 *
 * Publicar não edita a versão atual: cria uma nova, com autor, motivo e
 * aceite. Rollback é republicar uma versão antiga — o histórico nunca é
 * reescrito.
 *
 * @property-read Schema $form
 */
class PromptsERegras extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Treinamento';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Prompts e regras';

    protected string $view = 'filament.ia.pages.prompts-e-regras';

    public ?int $doctorId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->doctorId = AgenteSelecionado::resolver($this->usuario(), null);
        $this->preencher();
    }

    public function updatedDoctorId(): void
    {
        $this->doctorId = AgenteSelecionado::resolver($this->usuario(), $this->doctorId);
        $this->preencher();
    }

    public function form(Schema $schema): Schema
    {
        $campos = [];

        foreach (IaPromptVersion::BLOCOS as $chave => $rotulo) {
            $campos[] = Textarea::make("blocos.{$chave}")
                ->label($rotulo."  ({$chave})")
                ->rows($chave === 'HANDOFF_MSG' ? 3 : 8)
                ->columnSpanFull()
                ->autosize();
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Instruções da agente')
                    ->description('Isto vira o system prompt. As regras inegociáveis são adicionadas automaticamente no fim e prevalecem sobre o que estiver escrito aqui.')
                    ->schema($campos),

                Section::make('Publicação')
                    ->description('Cada publicação cria uma versão nova, registrada com seu nome.')
                    ->schema([
                        TextInput::make('motivo')
                            ->label('O que você mudou e por quê')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Fica no histórico. É o que permite descobrir depois qual alteração piorou a agente.'),
                        Checkbox::make('aceite')
                            ->label(PublicadorDePrompt::TEXTO_ACEITE)
                            ->required()
                            ->accepted()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public function publicar(): void
    {
        $state = $this->form->getState();

        try {
            $versao = app(PublicadorDePrompt::class)->publicar(
                doctorId: (int) $this->doctorId,
                blocos: $state['blocos'] ?? [],
                autor: $this->usuario(),
                motivo: $state['motivo'] ?? null,
                aceite: (bool) ($state['aceite'] ?? false),
                userAgent: request()->userAgent(),
            );
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Não foi possível publicar')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("Versão {$versao->versao} publicada")
            ->body('A agente já está usando estas instruções nas próximas respostas.')
            ->send();

        $this->preencher();
    }

    public function reverter(int $versaoId): void
    {
        $versao = IaPromptVersion::query()
            ->where('doctor_id', $this->doctorId)
            ->find($versaoId);

        if ($versao === null) {
            return;
        }

        try {
            $nova = app(PublicadorDePrompt::class)->reverter($versao, $this->usuario(), aceite: true);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Não foi possível reverter')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("Rollback publicado como versão {$nova->versao}")
            ->body("As instruções da versão {$versao->versao} voltaram a valer.")
            ->send();

        $this->preencher();
    }

    /** @return array<int, string> */
    public function guardrails(): array
    {
        return IaGuardrail::paraAgente((int) $this->doctorId);
    }

    /** @return Collection<int, IaPromptVersion> */
    public function historico(): Collection
    {
        return IaPromptVersion::query()
            ->with('autor')
            ->where('doctor_id', $this->doctorId)
            ->orderByDesc('versao')
            ->limit(20)
            ->get();
    }

    public function versaoAtiva(): ?IaPromptVersion
    {
        return IaPromptVersion::query()
            ->where('doctor_id', $this->doctorId)
            ->where('ativo', true)
            ->first();
    }

    /** @return array<int, string> */
    public function agentes(): array
    {
        return AgenteSelecionado::options($this->usuario());
    }

    private function preencher(): void
    {
        $ativa = $this->versaoAtiva();

        $blocos = [];

        foreach (array_keys(IaPromptVersion::BLOCOS) as $chave) {
            $blocos[$chave] = $ativa?->blocos[$chave] ?? null;
        }

        $this->form->fill([
            'blocos' => $blocos,
            'motivo' => null,
            'aceite' => false,
        ]);
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
