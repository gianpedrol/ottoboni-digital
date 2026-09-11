<?php

namespace App\Filament\Ia\Pages;

use App\Enums\IaGateModo;
use App\Enums\IaIntent;
use App\Models\AuditLog;
use App\Models\IaGateSetting;
use App\Models\User;
use App\Services\Ia\IaWebhookClient;
use App\Support\AgenteSelecionado;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Configuração do portão, por agente.
 *
 * O modelo aparece aqui em read-only de propósito: trocar modelo muda o custo
 * e o comportamento de todas as respostas de uma vez, é decisão técnica e não
 * de operação. O campo está no $guarded do IaGateSetting, então nem um POST
 * forjado altera.
 *
 * @property-read Schema $form
 */
class ConfiguracaoDaIa extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Configuração';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Configuração da IA';

    protected string $view = 'filament.ia.pages.configuracao-da-ia';

    public ?int $doctorId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

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
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Portão de aprovação')
                    ->description('Quem decide se a agente pode responder sozinha.')
                    ->schema([
                        Select::make('modo')
                            ->label('Modo')
                            ->options(IaGateModo::class)
                            ->required()
                            ->live()
                            ->helperText('Comece em treinamento. "Automático total" ignora a acurácia e só deve ser usado com a fila vazia e alguém olhando.'),
                        TextInput::make('limiar_acuracia')
                            ->label('Acurácia para liberar')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.5)
                            ->maxValue(1)
                            ->required()
                            ->helperText('0.90 = 90%. Abaixo disso o assunto continua na fila.'),
                        TextInput::make('kill_switch_acuracia')
                            ->label('Acurácia do freio de mão')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.5)
                            ->maxValue(1)
                            ->required()
                            ->helperText('Se a nota geral cair abaixo disso, tudo volta para a fila automaticamente.'),
                        TextInput::make('min_amostras')
                            ->label('Mínimo de revisões por assunto')
                            ->numeric()
                            ->minValue(5)
                            ->required()
                            ->helperText('Ninguém libera uma agente com 3 acertos de sorte.'),
                        TextInput::make('janela')
                            ->label('Tamanho da janela')
                            ->numeric()
                            ->minValue(10)
                            ->required()
                            ->helperText('Quantas revisões recentes entram na conta. A nota reflete como ela está hoje, não a média histórica.'),
                        CheckboxList::make('intents_sempre_revisa')
                            ->label('Assuntos que sempre passam por humano')
                            ->options(fn (): array => collect(IaIntent::cases())
                                ->mapWithKeys(fn (IaIntent $i): array => [$i->value => $i->getLabel()])
                                ->all())
                            ->columns(2)
                            ->columnSpanFull()
                            ->helperText('Nunca são liberados por acurácia. Recomendado manter "Não sei responder" marcado: é ele que faz a base crescer.'),
                    ])
                    ->columns(2),

                Section::make('Quando ninguém aprova')
                    ->schema([
                        TextInput::make('timeout_min')
                            ->label('Prazo antes de marcar como atrasado (minutos)')
                            ->numeric()
                            ->minValue(5)
                            ->required()
                            ->helperText('Passando disso o item é destacado e volta a notificar. A agente nunca envia sozinha por causa do prazo.'),
                        Toggle::make('dm_espera_ativa')
                            ->label('Mandar direct de espera')
                            ->helperText('Acolhe a pessoa enquanto a equipe revisa, sem responder a dúvida.')
                            ->live(),
                        Textarea::make('dm_espera_texto')
                            ->label('Texto do direct de espera')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn ($get): bool => (bool) $get('dm_espera_ativa'))
                            ->requiredIf('dm_espera_ativa', true),
                    ])
                    ->columns(2),

                Section::make('Avisos de pendência')
                    ->schema([
                        Toggle::make('notif_push')
                            ->label('Push no celular (FCM)')
                            ->helperText(filled(config('painel.ia.fcm_server_key'))
                                ? 'Chega como notificação no aparelho de quem registrou o painel.'
                                : 'Configure FCM_URL e FCM_SERVER_KEY no .env para ativar.'),
                        TagsInput::make('notif_emails')
                            ->label('E-mails do resumo')
                            ->placeholder('nome@clinica.com.br')
                            ->columnSpanFull()
                            ->helperText('Resumo dos pendentes enviado pelo vigia da fila.'),
                    ])
                    ->columns(2),
            ]);
    }

    public function salvar(): void
    {
        $state = $this->form->getState();
        $cfg = IaGateSetting::paraAgente((int) $this->doctorId);
        $antes = $cfg->only(['modo', 'limiar_acuracia', 'min_amostras', 'intents_sempre_revisa']);

        // O modelo não entra: está no $guarded do model justamente para não
        // ser alterável por esta tela nem por request forjado.
        $cfg->update([
            'modo' => $state['modo'],
            'limiar_acuracia' => $state['limiar_acuracia'],
            'kill_switch_acuracia' => $state['kill_switch_acuracia'],
            'min_amostras' => $state['min_amostras'],
            'janela' => $state['janela'],
            'intents_sempre_revisa' => $state['intents_sempre_revisa'] ?: IaGateSetting::SEMPRE_REVISA_PADRAO,
            'timeout_min' => $state['timeout_min'],
            'dm_espera_ativa' => $state['dm_espera_ativa'],
            'dm_espera_texto' => $state['dm_espera_texto'] ?? IaGateSetting::DM_ESPERA_PADRAO,
            'notif_push' => $state['notif_push'],
            'notif_emails' => $state['notif_emails'] ?? [],
            'atualizado_por' => $this->usuario()->id,
        ]);

        AuditLog::registrar('ia.mudou_config', [
            'doctor_id' => $this->doctorId,
            'antes' => $antes,
            'depois' => $cfg->only(['modo', 'limiar_acuracia', 'min_amostras', 'intents_sempre_revisa']),
        ]);

        Notification::make()->success()->title('Configuração salva')->send();
    }

    public function config(): IaGateSetting
    {
        return IaGateSetting::paraAgente((int) $this->doctorId);
    }

    public function n8nConfigurado(): bool
    {
        return IaWebhookClient::configurado();
    }

    /** @return array<string, string> */
    public function agentes(): array
    {
        return AgenteSelecionado::options($this->usuario());
    }

    private function preencher(): void
    {
        $cfg = IaGateSetting::paraAgente((int) $this->doctorId);

        $this->form->fill([
            'modo' => $cfg->modo->value,
            'limiar_acuracia' => $cfg->limiar_acuracia,
            'kill_switch_acuracia' => $cfg->kill_switch_acuracia,
            'min_amostras' => $cfg->min_amostras,
            'janela' => $cfg->janela,
            'intents_sempre_revisa' => $cfg->sempreRevisa(),
            'timeout_min' => $cfg->timeout_min,
            'dm_espera_ativa' => $cfg->dm_espera_ativa,
            'dm_espera_texto' => $cfg->dm_espera_texto ?? IaGateSetting::DM_ESPERA_PADRAO,
            'notif_push' => $cfg->notif_push,
            'notif_emails' => $cfg->notif_emails ?? [],
        ]);
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
