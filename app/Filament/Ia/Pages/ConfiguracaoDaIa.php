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
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Configuração do portão, por agente.
 *
 * O modelo aparece aqui em read-only de propósito: trocar modelo muda o custo
 * e o comportamento de todas as respostas de uma vez, é decisão técnica e não
 * de operação. O campo está fora do $fillable do IaGateSetting, então nem um
 * POST forjado altera.
 *
 * As acurácias ficam gravadas de 0 a 1, mas a tela mostra em % — ninguém da
 * equipe pensa em "0.90".
 *
 * @property-read Schema $form
 */
class ConfiguracaoDaIa extends Page
{
    use HasUnsavedDataChangesAlert;

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
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Radio::make('modo')
                            ->label('Modo')
                            ->options(IaGateModo::class)
                            ->descriptions(collect(IaGateModo::cases())
                                ->mapWithKeys(fn (IaGateModo $m): array => [$m->value => $m->getDescription()])
                                ->all())
                            ->required()
                            ->live(),
                        Callout::make('Sem rede de proteção')
                            ->description('Neste modo nenhuma resposta passa pela equipe e a acurácia é ignorada. Volte para "Automático por acurácia" assim que terminar de acompanhar.')
                            ->danger()
                            ->visible(fn (Get $get): bool => self::modoEscolhido($get) === IaGateModo::AutoTotal),
                        Fieldset::make('Critérios para liberar um assunto')
                            ->schema([
                                TextInput::make('limiar_acuracia')
                                    ->label('Acurácia para liberar')
                                    ->numeric()
                                    ->suffix('%')
                                    ->step(1)
                                    ->minValue(50)
                                    ->maxValue(100)
                                    ->required()
                                    ->helperText('Abaixo disso o assunto continua na fila.'),
                                TextInput::make('kill_switch_acuracia')
                                    ->label('Freio de mão')
                                    ->numeric()
                                    ->suffix('%')
                                    ->step(1)
                                    ->minValue(50)
                                    ->maxValue(100)
                                    ->lte('limiar_acuracia')
                                    ->validationMessages([
                                        'lte' => 'O freio de mão precisa ficar igual ou abaixo da acurácia para liberar.',
                                    ])
                                    ->required()
                                    ->helperText('Se a nota geral cair abaixo disso, tudo volta para a fila automaticamente.'),
                                TextInput::make('min_amostras')
                                    ->label('Mínimo de revisões por assunto')
                                    ->numeric()
                                    ->suffix('revisões')
                                    ->minValue(5)
                                    ->required()
                                    ->helperText('Ninguém libera uma agente com 3 acertos de sorte.'),
                                TextInput::make('janela')
                                    ->label('Tamanho da janela')
                                    ->numeric()
                                    ->suffix('revisões')
                                    ->minValue(10)
                                    ->required()
                                    ->helperText('Quantas revisões recentes entram na conta. A nota reflete como ela está hoje, não a média histórica.'),
                            ])
                            ->columns(2),
                        CheckboxList::make('intents_sempre_revisa')
                            ->label('Assuntos que sempre passam por humano')
                            ->options(fn (): array => collect(IaIntent::cases())
                                ->mapWithKeys(fn (IaIntent $i): array => [$i->value => $i->getLabel()])
                                ->all())
                            ->columns(2)
                            ->helperText('Nunca são liberados por acurácia. Recomendado manter "Não sei responder" marcado: é ele que faz a base crescer.'),
                    ]),

                Section::make('Quando ninguém aprova')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextInput::make('timeout_min')
                            ->label('Prazo antes de marcar como atrasado')
                            ->numeric()
                            ->suffix('min')
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
                            ->visible(fn (Get $get): bool => (bool) $get('dm_espera_ativa'))
                            ->requiredIf('dm_espera_ativa', true),
                    ])
                    ->columns(2),

                Section::make('Avisos de pendência')
                    ->icon(Heroicon::OutlinedBellAlert)
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

        // O modelo não entra: está fora do $fillable justamente para não
        // ser alterável por esta tela nem por request forjado.
        $cfg->update([
            'modo' => $state['modo'],
            'limiar_acuracia' => self::dePorcentagem($state['limiar_acuracia']),
            'kill_switch_acuracia' => self::dePorcentagem($state['kill_switch_acuracia']),
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

        $this->rememberData();

        Notification::make()
            ->success()
            ->title('Configuração salva')
            ->body('Vale a partir da próxima resposta da agente.')
            ->send();
    }

    public function config(): IaGateSetting
    {
        return IaGateSetting::paraAgente((int) $this->doctorId);
    }

    public function n8nConfigurado(): bool
    {
        return IaWebhookClient::configurado();
    }

    /** @return array<int, string> */
    public function agentes(): array
    {
        return AgenteSelecionado::options($this->usuario());
    }

    private function preencher(): void
    {
        $cfg = IaGateSetting::paraAgente((int) $this->doctorId);

        $this->form->fill([
            'modo' => $cfg->modo->value,
            'limiar_acuracia' => self::emPorcentagem($cfg->limiar_acuracia),
            'kill_switch_acuracia' => self::emPorcentagem($cfg->kill_switch_acuracia),
            'min_amostras' => $cfg->min_amostras,
            'janela' => $cfg->janela,
            'intents_sempre_revisa' => $cfg->sempreRevisa(),
            'timeout_min' => $cfg->timeout_min,
            'dm_espera_ativa' => $cfg->dm_espera_ativa,
            'dm_espera_texto' => $cfg->dm_espera_texto ?? IaGateSetting::DM_ESPERA_PADRAO,
            'notif_push' => $cfg->notif_push,
            'notif_emails' => $cfg->notif_emails ?? [],
        ]);

        $this->rememberData();
    }

    /** O Radio pode devolver o enum ou o valor cru, conforme o momento do ciclo. */
    private static function modoEscolhido(Get $get): ?IaGateModo
    {
        $modo = $get('modo');

        return $modo instanceof IaGateModo ? $modo : IaGateModo::tryFrom((string) $modo);
    }

    private static function emPorcentagem(float $fracao): float
    {
        return round($fracao * 100, 1);
    }

    private static function dePorcentagem(mixed $porcentagem): float
    {
        return round((float) $porcentagem / 100, 4);
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
