<?php

namespace App\Filament\Resources\FollowupPlans\Schemas;

use App\Enums\FollowupCanal;
use App\Enums\FollowupGatilho;
use App\Enums\FollowupModo;
use App\Models\Doctor;
use App\Repositories\LeadRepository;
use App\Services\Kommo\KommoException;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FollowupPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Régua')
                    ->schema([
                        Select::make('doctor_id')
                            ->label('Médico')
                            ->options(Doctor::query()->where('ativo', true)->pluck('nome', 'id'))
                            ->required()
                            ->live()
                            ->helperText('Define o funil do Kommo e a agente (Duda ou Luna) que executa.'),
                        TextInput::make('nome')
                            ->label('Nome da régua')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('gatilho')
                            ->label('Gatilho')
                            ->options(FollowupGatilho::class)
                            ->required()
                            ->live()
                            ->helperText('O que coloca um lead nesta régua.'),
                        Select::make('gatilho_config.status_id')
                            ->label('Etapa do funil')
                            ->options(fn ($get): array => self::etapasDoMedico($get('doctor_id')))
                            ->visible(fn ($get): bool => $get('gatilho') === FollowupGatilho::Etapa->value)
                            ->requiredIf('gatilho', FollowupGatilho::Etapa->value),
                        Select::make('gatilho_config.temperatura')
                            ->label('Temperatura')
                            ->options([
                                'Quente' => 'Quente',
                                'Morno' => 'Morno',
                                'Frio' => 'Frio',
                            ])
                            ->visible(fn ($get): bool => $get('gatilho') === FollowupGatilho::Temperatura->value)
                            ->requiredIf('gatilho', FollowupGatilho::Temperatura->value),
                        TextInput::make('gatilho_config.horas')
                            ->label('Horas sem resposta')
                            ->numeric()
                            ->default(48)
                            ->visible(fn ($get): bool => $get('gatilho') === FollowupGatilho::SemResposta->value)
                            ->requiredIf('gatilho', FollowupGatilho::SemResposta->value),
                        Toggle::make('ativo')
                            ->label('Ativa')
                            ->default(false)
                            ->helperText('Use o simulador antes de ativar — ninguém publica régua no escuro.'),
                    ])
                    ->columns(2),

                Section::make('Passos')
                    ->description('Quantos passos quiser. O tempo de cada passo conta a partir do momento em que o lead entra na régua.')
                    ->schema([
                        Repeater::make('steps')
                            ->relationship('steps')
                            ->hiddenLabel()
                            ->orderColumn('ordem')
                            ->reorderable()
                            ->defaultItems(1)
                            ->addActionLabel('Adicionar passo')
                            ->itemLabel(fn (array $state): string => 'Passo — ' . ($state['offset_horas'] ?? '?') . 'h depois do gatilho')
                            ->schema([
                                TextInput::make('offset_horas')
                                    ->label('Quando (horas após o gatilho)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->suffix('horas')
                                    ->helperText('24 = 1 dia · 72 = 3 dias · 168 = 1 semana'),
                                Select::make('canal')
                                    ->label('Canal')
                                    ->options(FollowupCanal::class)
                                    ->default(FollowupCanal::KommoTask->value)
                                    ->required()
                                    ->live()
                                    ->helperText('Na Fase 2 o caminho seguro é "Tarefa no Kommo". Instagram e automático dependem do n8n.'),
                                Select::make('modo')
                                    ->label('Texto')
                                    ->options(FollowupModo::class)
                                    ->default(FollowupModo::TextoFixo->value)
                                    ->required()
                                    ->live(),
                                TextInput::make('kommo_bot_id')
                                    ->label('ID do Salesbot (opcional)')
                                    ->numeric()
                                    ->visible(fn ($get): bool => $get('canal') === FollowupCanal::KommoBot->value)
                                    ->helperText('Vazio usa o Salesbot configurado no médico.'),
                                Textarea::make('texto')
                                    ->label('Mensagem')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->requiredIf('modo', FollowupModo::TextoFixo->value)
                                    ->helperText('Aceita {nome}, {procedimento}, {temperatura} e {instagram} — preenchidos só com o que está registrado no lead. No modo IA este texto é o fallback quando a validação reprova o texto gerado.'),
                                Textarea::make('prompt_ia')
                                    ->label('Prompt para a IA')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn ($get): bool => $get('modo') === FollowupModo::Ia->value)
                                    ->requiredIf('modo', FollowupModo::Ia->value)
                                    ->helperText('A IA nunca inventa contexto: usa só o registro do lead e vocabulário seguro. Fora disso, cai no texto acima.'),
                                Toggle::make('ativo')
                                    ->label('Passo ativo')
                                    ->default(true),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function etapasDoMedico(mixed $doctorId): array
    {
        if (blank($doctorId)) {
            return [];
        }

        $doctor = Doctor::query()->find($doctorId);

        if ($doctor === null) {
            return [];
        }

        try {
            $pipeline = app(LeadRepository::class)->pipelines()
                ->first(fn ($p): bool => $p->id === (int) $doctor->kommo_pipeline_id);
        } catch (KommoException) {
            return [];
        }

        if ($pipeline === null) {
            return [];
        }

        $options = [];

        foreach ($pipeline->statuses as $status) {
            if (! in_array($status->id, [(int) config('kommo.status_ganho'), (int) config('kommo.status_perdido')], true)) {
                $options[$status->id] = $status->name;
            }
        }

        return $options;
    }
}
