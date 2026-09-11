<?php

namespace App\Filament\Resources\AutomationRules\Schemas;

use App\Enums\EtapaCanonica;
use App\Enums\ModoAutomacao;
use App\Models\FollowupPlan;
use App\Services\Automacoes\CamposDoLead;
use App\Services\Automacoes\DescritorDeRegra;
use App\Services\Automacoes\NomesDoKommo;
use App\Services\Automacoes\NormalizadorDeRegra;
use App\Support\MapaDeEtapas;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AutomationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Regra')
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->placeholder('A8')
                            ->helperText('Curto, para achar a regra no log de execuções.'),
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                        CheckboxList::make('pipeline_ids')
                            ->label('Vale nos pipelines')
                            ->options(self::pipelines())
                            ->required()
                            ->columns(2),
                        Select::make('modo')
                            ->label('Modo')
                            ->options(ModoAutomacao::class)
                            ->default(ModoAutomacao::SoRegistrar->value)
                            ->required()
                            ->helperText('Toda regra nasce em “só registrar”. No protótipo nada é gravado no Kommo em modo nenhum.'),
                        TextInput::make('ordem')
                            ->label('Ordem na lista')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Quando (gatilho)')
                    ->description('O que acontece no Kommo para a regra ser avaliada.')
                    ->schema([
                        Group::make([
                            ...self::camposDoGatilho(),
                            Repeater::make('ou')
                                ->label('Ou também quando')
                                ->schema(self::camposDoGatilho())
                                ->columns(2)
                                ->defaultItems(0)
                                ->addActionLabel('Adicionar gatilho alternativo')
                                ->itemLabel(fn (array $state): string => filled($state['tipo'] ?? null) ? DescritorDeRegra::umGatilho($state) : 'Gatilho alternativo')
                                ->columnSpanFull(),
                        ])
                            ->statePath('gatilho')
                            ->columns(2),
                    ]),

                Section::make('Se (condições)')
                    ->description('Avaliadas depois do evento; todas precisam ser verdadeiras. Vazio = sem condição.')
                    ->schema([
                        Repeater::make('condicoes')
                            ->hiddenLabel()
                            ->schema(self::camposDaCondicao())
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar condição')
                            ->itemLabel(fn (array $state): string => filled($state['tipo'] ?? null) ? DescritorDeRegra::condicao(self::escalares($state)) : 'Condição'),
                    ]),

                Section::make('Então (ações)')
                    ->description('Executadas em ordem; cada ação parte do lead como a anterior o deixou.')
                    ->schema([
                        Builder::make('acoes')
                            ->hiddenLabel()
                            ->blocks(self::blocos())
                            ->addActionLabel('Adicionar ação')
                            ->required()
                            ->minItems(1)
                            ->collapsible()
                            ->blockPickerColumns(2),
                    ]),
            ]);
    }

    /**
     * Registro (formato do motor) → estado do formulário.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paraPreencher(array $data): array
    {
        $data['gatilho'] = (array) ($data['gatilho'] ?? []);
        $data['condicoes'] = (array) ($data['condicoes'] ?? []);
        $data['acoes'] = NormalizadorDeRegra::paraBuilder((array) ($data['acoes'] ?? []));

        return $data;
    }

    /**
     * Estado do formulário → formato do motor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paraSalvar(array $data): array
    {
        $data['gatilho'] = NormalizadorDeRegra::gatilho((array) ($data['gatilho'] ?? []));
        $data['condicoes'] = NormalizadorDeRegra::condicoes((array) ($data['condicoes'] ?? []));
        $data['acoes'] = NormalizadorDeRegra::doBuilder((array) ($data['acoes'] ?? []));
        $data['pipeline_ids'] = NormalizadorDeRegra::pipelines((array) ($data['pipeline_ids'] ?? []));

        return $data;
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private static function camposDoGatilho(): array
    {
        $tipo = fn (string ...$tipos) => fn ($get): bool => in_array($get('tipo'), $tipos, true);

        return [
            Select::make('tipo')
                ->label('Tipo')
                ->options(DescritorDeRegra::TIPOS_GATILHO)
                ->required()
                ->live(),
            Select::make('etapas')
                ->label('Etapas')
                ->multiple()
                ->options(EtapaCanonica::class)
                ->visible($tipo('etapa'))
                ->required($tipo('etapa'))
                ->helperText('Etapa canônica: vale em qualquer pipeline pelo mapa de etapas.'),
            Select::make('campo')
                ->label('Campo')
                ->options(CamposDoLead::opcoes())
                ->visible($tipo('campo_alterado', 'campo_preenchido'))
                ->required($tipo('campo_alterado', 'campo_preenchido'))
                ->live(),
            TextInput::make('valor')
                ->label('Novo valor')
                ->visible($tipo('campo_alterado'))
                ->required($tipo('campo_alterado'))
                ->datalist(fn ($get): array => CamposDoLead::OPCOES[$get('campo')] ?? []),
            TextInput::make('tag')
                ->label('Tag')
                ->visible($tipo('tag_adicionada'))
                ->required($tipo('tag_adicionada')),
            TextInput::make('horas')
                ->label('Parado há')
                ->numeric()
                ->minValue(1)
                ->suffix('horas')
                ->visible($tipo('inatividade'))
                ->required($tipo('inatividade')),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    private static function camposDaCondicao(): array
    {
        $tipo = fn (string ...$tipos) => fn ($get): bool => in_array($get('tipo'), $tipos, true);

        return [
            Select::make('tipo')
                ->label('Condição')
                ->options(DescritorDeRegra::TIPOS_CONDICAO)
                ->required()
                ->live(),
            Select::make('etapas')
                ->label('Etapas')
                ->multiple()
                ->options(EtapaCanonica::class)
                ->visible($tipo('etapa_atual'))
                ->required($tipo('etapa_atual')),
            Select::make('campo')
                ->label('Campo')
                ->options(CamposDoLead::opcoes())
                ->visible($tipo('campo_preenchido', 'campo_igual'))
                ->required($tipo('campo_preenchido', 'campo_igual'))
                ->live(),
            TextInput::make('valor')
                ->label('Valor')
                ->visible($tipo('campo_igual'))
                ->required($tipo('campo_igual'))
                ->datalist(fn ($get): array => CamposDoLead::OPCOES[$get('campo')] ?? []),
            TextInput::make('tag')
                ->label('Tag')
                ->visible($tipo('tem_tag'))
                ->required($tipo('tem_tag')),
        ];
    }

    /**
     * Um bloco por tipo de ação do motor.
     *
     * @return array<int, Block>
     */
    private static function blocos(): array
    {
        $rotulo = DescritorDeRegra::TIPOS_ACAO;

        return [
            Block::make('mover_etapa')
                ->label($rotulo['mover_etapa'])
                ->icon(Heroicon::OutlinedArrowRightCircle)
                ->schema([
                    Select::make('etapa')
                        ->label('Para a etapa')
                        ->options(EtapaCanonica::class)
                        ->required()
                        ->helperText('Vira o status_id certo de cada pipeline pelo mapa de etapas. Lead que já está lá não é movido.'),
                ]),
            Block::make('adicionar_tag')
                ->label($rotulo['adicionar_tag'])
                ->icon(Heroicon::OutlinedTag)
                ->schema([
                    TextInput::make('tag')
                        ->label('Tag')
                        ->required()
                        ->helperText('Use {fup} para o número da etapa de FUP: Contato{fup} vira Contato1, Contato2 ou Contato3.'),
                ]),
            Block::make('remover_tags')
                ->label($rotulo['remover_tags'])
                ->icon(Heroicon::OutlinedBackspace)
                ->schema([
                    TagsInput::make('tags')
                        ->label('Tags')
                        ->required()
                        ->helperText('Tag colocada pela própria regra não é removida.'),
                ]),
            Block::make('preencher_campo')
                ->label($rotulo['preencher_campo'])
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Select::make('campo')
                        ->label('Campo')
                        ->options(CamposDoLead::opcoes())
                        ->required()
                        ->live(),
                    TextInput::make('valor')
                        ->label('Valor')
                        ->required()
                        ->datalist(fn ($get): array => CamposDoLead::OPCOES[$get('campo')] ?? []),
                ])
                ->columns(2),
            Block::make('criar_tarefa')
                ->label($rotulo['criar_tarefa'])
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->schema([
                    TextInput::make('texto')
                        ->label('Texto da tarefa')
                        ->required(),
                    TextInput::make('prazo_horas')
                        ->label('Prazo')
                        ->numeric()
                        ->minValue(1)
                        ->default(24)
                        ->suffix('horas'),
                ])
                ->columns(2),
            Block::make('criar_card_pipeline')
                ->label($rotulo['criar_card_pipeline'])
                ->icon(Heroicon::OutlinedSquare2Stack)
                ->schema([
                    Select::make('pipeline')
                        ->label('Pipeline de destino')
                        ->options(['cirurgias' => 'Fluxo cirurgias'])
                        ->default('cirurgias')
                        ->required(),
                    TextInput::make('etapa')
                        ->label('Etapa de destino (status_id)')
                        ->numeric()
                        ->helperText('Vazio = etapa de entrada do pipeline.'),
                    CheckboxList::make('copiar_campos')
                        ->label('Copiar do lead')
                        ->options(CamposDoLead::opcoes())
                        ->columns(3)
                        ->columnSpanFull()
                        ->helperText('O card nasce com o mesmo contato do lead.'),
                ])
                ->columns(2),
            Block::make('iniciar_regua')
                ->label($rotulo['iniciar_regua'])
                ->icon(Heroicon::OutlinedPlayCircle)
                ->schema([
                    Select::make('followup_plan_id')
                        ->label('Régua')
                        ->options(fn (): array => FollowupPlan::query()->orderBy('nome')->pluck('nome', 'id')->all())
                        ->placeholder('A régua ligada à etapa atual do lead')
                        ->helperText('Réguas da Fase 2 (Follow-up).'),
                ]),
            Block::make('cancelar_reguas')
                ->label($rotulo['cancelar_reguas'])
                ->icon(Heroicon::OutlinedStopCircle)
                ->schema([
                    Text::make('Cancela os follow-ups agendados deste lead nas réguas da Fase 2.'),
                ]),
            Block::make('disparar_salesbot')
                ->label($rotulo['disparar_salesbot'])
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([
                    TextInput::make('bot_id')
                        ->label('ID do Salesbot')
                        ->numeric()
                        ->helperText('Vazio = Salesbot configurado no médico.'),
                ]),
            Block::make('criar_recebivel')
                ->label($rotulo['criar_recebivel'])
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Text::make('Gera a conta a receber no Financeiro com o Valor total do lead (ou o Valor da proposta).'),
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function pipelines(): array
    {
        $nomes = new NomesDoKommo;
        $opcoes = [];

        foreach (MapaDeEtapas::pipelinesMapeados() as $id) {
            $opcoes[$id] = $nomes->pipeline($id);
        }

        return $opcoes;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function escalares(array $state): array
    {
        return array_map(
            fn ($v) => is_array($v) ? array_map(NormalizadorDeRegra::escalar(...), $v) : NormalizadorDeRegra::escalar($v),
            $state,
        );
    }
}
