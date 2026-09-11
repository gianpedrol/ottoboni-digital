<?php

namespace App\Filament\Pages;

use App\Enums\EtapaCanonica;
use App\Enums\StatusExecucaoAutomacao;
use App\Filament\Resources\AutomationRuns\AutomationRunResource;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\User;
use App\Repositories\LeadRepository;
use App\Services\Automacoes\CamposDoLead;
use App\Services\Automacoes\EventoSimulado;
use App\Services\Automacoes\LeadsDeExemplo;
use App\Services\Automacoes\MotorDeAutomacoes;
use App\Services\Automacoes\NomesDoKommo;
use App\Services\Automacoes\NormalizadorDeRegra;
use App\Services\Kommo\DTO\LeadData;
use App\Services\Kommo\KommoException;
use App\Support\Demonstracao;
use App\Support\MapaDeEtapas;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Roda uma regra contra um lead (real, só leitura, ou de exemplo) e mostra
 * passo a passo o que ela faria, com o payload de cada chamada. Nada é
 * gravado no Kommo.
 *
 * @property-read Schema $form
 */
class SimuladorAutomacao extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Automações';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Simulador de automação';

    protected static ?string $navigationLabel = 'Simulador';

    protected static ?string $slug = 'automacoes/simulador';

    protected string $view = 'filament.pages.simulador-automacao';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $resultado = null;

    public bool $registrado = false;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    public function mount(): void
    {
        $regraId = request()->integer('regra')
            ?: AutomationRule::query()->orderBy('ordem')->orderBy('id')->value('id');

        $this->form->fill([
            'regra_id' => $regraId,
            'fonte' => 'exemplo',
            ...self::sugestoes($regraId),
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ($this->data['fonte'] ?? 'exemplo') === 'exemplo' ? Demonstracao::selo() : null;
    }

    public function form(Schema $schema): Schema
    {
        $fonte = fn (string $valor) => fn ($get): bool => $get('fonte') === $valor;
        $evento = fn (string $valor) => fn ($get): bool => $get('evento_tipo') === $valor;

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Regra e lead')
                    ->schema([
                        Select::make('regra_id')
                            ->label('Regra')
                            ->options(fn (): array => AutomationRule::query()
                                ->orderBy('ordem')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (AutomationRule $r): array => [$r->id => "{$r->rotulo()} ({$r->modo->getLabel()})"])
                                ->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set): void {
                                foreach (self::sugestoes(filled($state) ? (int) $state : null) as $campo => $valor) {
                                    $set($campo, $valor);
                                }

                                $this->resultado = null;
                            })
                            ->helperText('Ao trocar a regra, o lead de exemplo e o evento que a disparam já vêm preenchidos.')
                            ->columnSpanFull(),
                        ToggleButtons::make('fonte')
                            ->label('De onde vem o lead')
                            ->options([
                                'exemplo' => 'Lead de exemplo',
                                'kommo' => 'Lead real do Kommo',
                            ])
                            ->icons([
                                'exemplo' => Heroicon::OutlinedBeaker,
                                'kommo' => Heroicon::OutlinedCloudArrowDown,
                            ])
                            ->inline()
                            ->required()
                            ->live(),
                        Select::make('exemplo')
                            ->label('Lead de exemplo')
                            ->options(LeadsDeExemplo::opcoes())
                            ->visible($fonte('exemplo'))
                            ->required($fonte('exemplo'))
                            ->helperText('Dados fictícios, com os IDs reais das etapas. Funciona sem Kommo.'),
                        TextInput::make('lead_id')
                            ->label('ID do lead no Kommo')
                            ->numeric()
                            ->visible($fonte('kommo'))
                            ->required($fonte('kommo'))
                            ->helperText('Somente leitura: o lead é lido do Kommo e nada é gravado. O ID aparece na URL do lead.'),
                    ])
                    ->columns(2),
                Section::make('Evento simulado')
                    ->description('O que aconteceu no Kommo. Sem evento, a regra é avaliada contra o estado atual do lead.')
                    ->schema([
                        Select::make('evento_tipo')
                            ->label('Evento')
                            ->options(['estado' => 'Sem evento: avaliar o estado atual', ...EventoSimulado::TIPOS])
                            ->required()
                            ->live(),
                        Select::make('evento_etapa')
                            ->label('Etapa')
                            ->options(EtapaCanonica::class)
                            ->visible($evento('etapa'))
                            ->required($evento('etapa')),
                        Select::make('evento_campo')
                            ->label('Campo')
                            ->options(CamposDoLead::opcoes())
                            ->visible($evento('campo_alterado'))
                            ->required($evento('campo_alterado'))
                            ->live(),
                        TextInput::make('evento_valor')
                            ->label('Novo valor')
                            ->visible($evento('campo_alterado'))
                            ->datalist(fn ($get): array => CamposDoLead::OPCOES[$get('evento_campo')] ?? [])
                            ->helperText('Vazio = campo apagado.'),
                        TextInput::make('evento_tag')
                            ->label('Tag')
                            ->visible($evento('tag_adicionada'))
                            ->required($evento('tag_adicionada')),
                        TextInput::make('evento_horas')
                            ->label('Horas parado')
                            ->numeric()
                            ->minValue(1)
                            ->visible($evento('inatividade'))
                            ->required($evento('inatividade')),
                        Toggle::make('evento_integracao')
                            ->label('Alteração feita pelo próprio painel (teste do anti-loop)')
                            ->helperText('Quando o painel escreve no Kommo, o Kommo avisa de volta. Esse aviso é ignorado para a regra não se disparar em loop.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function simular(): void
    {
        $state = $this->form->getState();

        $regra = AutomationRule::query()->findOrFail($state['regra_id']);
        $motor = app(MotorDeAutomacoes::class);
        $extras = [];

        if (($state['fonte'] ?? 'exemplo') === 'kommo') {
            $repo = app(LeadRepository::class);

            try {
                $lead = $repo->find((int) $state['lead_id']);
            } catch (KommoException $e) {
                Notification::make()
                    ->danger()
                    ->title('Não foi possível ler o lead no Kommo')
                    ->body("Confira o ID e se o token do Kommo está configurado. Para demonstrar sem Kommo, use um lead de exemplo. Detalhe: {$e->getMessage()}")
                    ->send();

                return;
            }

            if ($lead === null) {
                Notification::make()
                    ->warning()
                    ->title('Lead não encontrado no Kommo')
                    ->body("Nenhum lead com o ID {$state['lead_id']}.")
                    ->send();

                return;
            }

            // Nomes reais das etapas, se o Kommo responder; senão ficam os de referência.
            try {
                $motor = $motor->comNomes(NomesDoKommo::comPipelines($repo->pipelines()));
            } catch (KommoException) {
            }

            $fonte = 'Kommo · lido agora, somente leitura';
        } else {
            $chave = (string) $state['exemplo'];
            $lead = LeadsDeExemplo::lead($chave);
            $extras = LeadsDeExemplo::camposExtras($chave);
            $fonte = 'Lead de exemplo · dados fictícios';
        }

        $avaliacao = $motor->avaliar($regra, $lead, self::eventoDoFormulario($state), $extras);

        $this->resultado = [
            ...$avaliacao->toArray(),
            'regra' => $regra->rotulo(),
            'regra_id' => $regra->id,
            'modo' => $regra->modo->getLabel(),
            'fonte' => $state['fonte'] ?? 'exemplo',
            'lead' => self::resumoDoLead($lead, $extras, $motor->nomes(), $fonte),
        ];

        $this->registrado = false;
    }

    public function registrar(): void
    {
        $r = $this->resultado;

        if ($r === null || ! ($r['casou'] ?? false) || $this->registrado) {
            return;
        }

        $run = AutomationRun::query()->create([
            'automation_rule_id' => $r['regra_id'],
            'kommo_lead_id' => $r['lead']['id'],
            'lead_nome' => $r['lead']['nome'],
            'pipeline_id' => $r['lead']['pipeline_id'],
            'evento' => $r['evento'],
            'acoes' => $r['acoes'],
            'status' => StatusExecucaoAutomacao::Registrado,
            'motivo' => 'Execução simulada no painel — nada foi enviado ao Kommo.',
            'demo' => $r['fonte'] === 'exemplo',
        ]);

        AuditLog::registrar('registrou_execucao_simulada', [
            'rule_id' => $r['regra_id'],
            'run_id' => $run->id,
        ]);

        $this->registrado = true;

        Notification::make()
            ->success()
            ->title('Execução simulada registrada')
            ->body('Ficou no log de execuções com status “Só registrado”.')
            ->actions([
                Action::make('ver')
                    ->label('Ver execução')
                    ->url(AutomationRunResource::getUrl('view', ['record' => $run])),
            ])
            ->send();
    }

    /**
     * Lead de exemplo e evento que fazem a regra disparar.
     *
     * @return array<string, mixed>
     */
    public static function sugestoes(?int $regraId): array
    {
        $regra = $regraId !== null ? AutomationRule::query()->find($regraId) : null;
        $exemplo = LeadsDeExemplo::sugeridoPara($regra?->codigo);
        $evento = $regra !== null ? EventoSimulado::sugeridoPara((array) $regra->gatilho) : null;

        // Gatilho de etapa: sugere entrar numa etapa em que o lead ainda não está.
        if ($evento !== null && $evento->tipo === 'etapa' && $regra !== null) {
            $lead = LeadsDeExemplo::lead($exemplo);
            $atual = MapaDeEtapas::canonica($lead->pipelineId, $lead->statusId)?->value;

            foreach ((array) ($regra->gatilho['etapas'] ?? []) as $etapa) {
                if ($etapa !== $atual) {
                    $evento = EventoSimulado::entrouNaEtapa((string) $etapa);
                    break;
                }
            }
        }

        return [
            'exemplo' => $exemplo,
            'evento_tipo' => $evento !== null ? $evento->tipo : 'estado',
            'evento_etapa' => $evento?->etapa,
            'evento_campo' => $evento?->campo,
            'evento_valor' => $evento?->valor,
            'evento_tag' => $evento?->tag,
            'evento_horas' => $evento?->horas,
            'evento_integracao' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function eventoDoFormulario(array $state): ?EventoSimulado
    {
        $tipo = (string) ($state['evento_tipo'] ?? 'estado');

        if ($tipo === 'estado') {
            return null;
        }

        $texto = fn (string $chave): ?string => filled($state[$chave] ?? null)
            ? (string) NormalizadorDeRegra::escalar($state[$chave])
            : null;

        $evento = new EventoSimulado(
            tipo: $tipo,
            etapa: $texto('evento_etapa'),
            campo: $texto('evento_campo'),
            valor: $texto('evento_valor'),
            tag: $texto('evento_tag'),
            horas: filled($state['evento_horas'] ?? null) ? (int) $state['evento_horas'] : null,
        );

        return ! empty($state['evento_integracao']) ? $evento->daIntegracao() : $evento;
    }

    /**
     * @param  array<string, string>  $extras
     * @return array<string, mixed>
     */
    private static function resumoDoLead(LeadData $lead, array $extras, NomesDoKommo $nomes, string $fonte): array
    {
        $etapa = MapaDeEtapas::canonica($lead->pipelineId, $lead->statusId);
        $nomeEtapa = $nomes->status($lead->pipelineId, $lead->statusId);
        $campos = [];

        foreach ([...CamposDoLead::doLead($lead), ...$extras] as $campo => $valor) {
            if (filled($valor)) {
                $campos[CamposDoLead::rotulo($campo)] = $valor;
            }
        }

        $pipeline = $nomes->pipeline($lead->pipelineId);
        $etapaTexto = $etapa !== null && $etapa->getLabel() !== $nomeEtapa ? "{$nomeEtapa} ({$etapa->getLabel()})" : $nomeEtapa;

        return [
            'id' => $lead->id,
            'nome' => $lead->name,
            'pipeline_id' => $lead->pipelineId,
            'pipeline' => $pipeline,
            'etapa' => $etapaTexto,
            'tags' => $lead->tags,
            'campos' => $campos,
            'fonte' => $fonte,
            'resumo' => "#{$lead->id} · {$pipeline} · {$etapaTexto}",
        ];
    }
}
