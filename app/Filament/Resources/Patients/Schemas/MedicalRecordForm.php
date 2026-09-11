<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\TipoRegistroProntuario;
use App\Filament\Resources\Patients\FichaDoPaciente;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Formulário do registro de prontuário. Os campos mudam conforme o tipo;
 * o que é estruturado vai para `dados` (cifrado), o texto livre para
 * `conteudo` (cifrado) e as fotos para `anexos`.
 */
class MedicalRecordForm
{
    public static function configure(Schema $schema, Patient $patient): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('tipo')
                    ->label('Tipo de registro')
                    ->options(TipoRegistroProntuario::class)
                    ->default(TipoRegistroProntuario::Anamnese->value)
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                        $novo = self::comoTipo($state);
                        $anterior = self::comoTipo($old);

                        // Só troca o título se o usuário ainda não escreveu um próprio.
                        if ($novo !== null && (blank($get('titulo')) || $get('titulo') === $anterior?->tituloPadrao())) {
                            $set('titulo', $novo->tituloPadrao());
                        }
                    }),
                TextInput::make('titulo')
                    ->label('Título')
                    ->default(TipoRegistroProntuario::Anamnese->tituloPadrao())
                    ->required()
                    ->maxLength(160),
                Select::make('appointment_id')
                    ->label('Consulta vinculada')
                    ->options(fn (): array => self::consultasDoPaciente($patient))
                    ->placeholder('Sem vínculo')
                    ->columnSpanFull(),

                self::anamnese(),
                self::evolucao(),
                self::prescricao(),
                self::exame(),
                self::atestado(),
                self::fotos(),
            ]);
    }

    private static function anamnese(): Section
    {
        return Section::make('Anamnese')
            ->visible(fn (Get $get): bool => self::comoTipo($get('tipo')) === TipoRegistroProntuario::Anamnese)
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Textarea::make('dados.queixa_principal')
                    ->label('Queixa principal')
                    ->rows(2)
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('dados.historia_doenca_atual')
                    ->label('História da doença atual')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('dados.antecedentes')
                    ->label('Antecedentes pessoais e familiares')
                    ->rows(2),
                Textarea::make('dados.cirurgias_previas')
                    ->label('Cirurgias prévias')
                    ->rows(2),
                TextInput::make('dados.alergias')
                    ->label('Alergias')
                    ->placeholder('Nega'),
                Textarea::make('dados.medicacoes_em_uso')
                    ->label('Medicações em uso')
                    ->rows(2),
                Select::make('dados.tabagismo')
                    ->label('Tabagismo')
                    ->options(['nao' => 'Não', 'ex' => 'Ex-tabagista', 'sim' => 'Sim']),
                Select::make('dados.etilismo')
                    ->label('Etilismo')
                    ->options(['nao' => 'Não', 'social' => 'Social', 'frequente' => 'Frequente']),
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('dados.peso')
                            ->label('Peso')
                            ->numeric()
                            ->step(0.1)
                            ->suffix('kg')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('dados.imc', self::imc($get('dados.peso'), $get('dados.altura')))),
                        TextInput::make('dados.altura')
                            ->label('Altura')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('m')
                            ->placeholder('1,65')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('dados.imc', self::imc($get('dados.peso'), $get('dados.altura')))),
                        TextInput::make('dados.imc')
                            ->label('IMC')
                            ->readOnly()
                            ->helperText('Calculado pelo peso e altura.'),
                    ]),
                Textarea::make('dados.expectativas')
                    ->label('Expectativas do paciente')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function evolucao(): Section
    {
        return Section::make('Evolução')
            ->visible(fn (Get $get): bool => self::comoTipo($get('tipo')) === TipoRegistroProntuario::Evolucao)
            ->columnSpanFull()
            ->schema([
                RichEditor::make('conteudo')
                    ->hiddenLabel()
                    ->required()
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ]),
            ]);
    }

    private static function prescricao(): Section
    {
        return Section::make('Prescrição')
            ->visible(fn (Get $get): bool => self::comoTipo($get('tipo')) === TipoRegistroProntuario::Prescricao)
            ->columnSpanFull()
            ->schema([
                Repeater::make('dados.itens')
                    ->label('Medicamentos')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Adicionar medicamento')
                    ->columns(4)
                    ->schema([
                        TextInput::make('medicamento')
                            ->label('Medicamento')
                            ->required(),
                        TextInput::make('dose')
                            ->label('Dose')
                            ->placeholder('500 mg'),
                        TextInput::make('posologia')
                            ->label('Posologia')
                            ->placeholder('1 comprimido de 8/8h'),
                        TextInput::make('duracao')
                            ->label('Duração')
                            ->placeholder('7 dias'),
                    ]),
                Textarea::make('conteudo')
                    ->label('Orientações')
                    ->rows(2),
            ]);
    }

    private static function exame(): Section
    {
        return Section::make('Pedido de exames')
            ->visible(fn (Get $get): bool => self::comoTipo($get('tipo')) === TipoRegistroProntuario::Exame)
            ->columnSpanFull()
            ->schema([
                Repeater::make('dados.exames')
                    ->label('Exames')
                    ->simple(
                        TextInput::make('exame')
                            ->placeholder('Hemograma completo')
                            ->required(),
                    )
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Adicionar exame'),
                Textarea::make('dados.indicacao_clinica')
                    ->label('Indicação clínica')
                    ->rows(2),
            ]);
    }

    private static function atestado(): Section
    {
        return Section::make('Atestado')
            ->visible(fn (Get $get): bool => self::comoTipo($get('tipo')) === TipoRegistroProntuario::Atestado)
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                TextInput::make('dados.dias')
                    ->label('Dias de afastamento')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(180)
                    ->required(),
                DatePicker::make('dados.inicio')
                    ->label('A partir de')
                    ->displayFormat('d/m/Y')
                    ->default(now()->toDateString()),
                TextInput::make('dados.cid')
                    ->label('CID (opcional)')
                    ->maxLength(10)
                    ->helperText('Só com autorização expressa do paciente.'),
                Textarea::make('conteudo')
                    ->label('Texto do atestado')
                    ->rows(3)
                    ->columnSpanFull()
                    ->default('Atesto, para os devidos fins, que o(a) paciente esteve sob meus cuidados e necessita de afastamento de suas atividades pelo período indicado.'),
            ]);
    }

    private static function fotos(): Section
    {
        return Section::make('Fotos antes/depois')
            ->description('No protótipo guardamos só o nome do arquivo e a legenda. No produto as fotos ficam em armazenamento privado, fora da pasta pública.')
            ->visible(fn (Get $get): bool => self::comoTipo($get('tipo')) === TipoRegistroProntuario::Foto)
            ->columnSpanFull()
            ->schema([
                Repeater::make('anexos')
                    ->label('Fotos')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Adicionar foto')
                    ->columns(4)
                    ->schema([
                        Select::make('momento')
                            ->label('Momento')
                            ->options(['antes' => 'Antes', 'depois' => 'Depois'])
                            ->required(),
                        Select::make('angulo')
                            ->label('Ângulo')
                            ->options(MedicalRecord::ANGULOS)
                            ->required(),
                        TextInput::make('arquivo')
                            ->label('Arquivo')
                            ->placeholder('IMG_2031.jpg'),
                        TextInput::make('legenda')
                            ->label('Legenda'),
                    ]),
                Textarea::make('conteudo')
                    ->label('Observações')
                    ->rows(2),
            ]);
    }

    /**
     * Normaliza o que veio do formulário antes de gravar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepararDados(array $data): array
    {
        if (isset($data['dados']) && is_array($data['dados'])) {
            foreach (['itens', 'exames'] as $lista) {
                if (isset($data['dados'][$lista]) && is_array($data['dados'][$lista])) {
                    $data['dados'][$lista] = array_values($data['dados'][$lista]);
                }
            }

            if (array_key_exists('peso', $data['dados']) || array_key_exists('altura', $data['dados'])) {
                $data['dados']['imc'] = self::imc($data['dados']['peso'] ?? null, $data['dados']['altura'] ?? null);
            }
        }

        if (isset($data['anexos']) && is_array($data['anexos'])) {
            $data['anexos'] = array_values($data['anexos']);
        }

        return $data;
    }

    /**
     * IMC com uma casa. Aceita altura em metros (1,65) ou centímetros (165).
     */
    public static function imc(mixed $peso, mixed $altura): ?float
    {
        $peso = (float) str_replace(',', '.', (string) $peso);
        $altura = (float) str_replace(',', '.', (string) $altura);

        if ($altura > 3) {
            $altura /= 100;
        }

        if ($peso <= 0 || $altura <= 0) {
            return null;
        }

        return round($peso / ($altura ** 2), 1);
    }

    public static function comoTipo(mixed $valor): ?TipoRegistroProntuario
    {
        if ($valor instanceof TipoRegistroProntuario) {
            return $valor;
        }

        return is_string($valor) ? TipoRegistroProntuario::tryFrom($valor) : null;
    }

    /**
     * @return array<int, string>
     */
    private static function consultasDoPaciente(Patient $patient): array
    {
        $tz = FichaDoPaciente::tz();

        return Appointment::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('inicio')
            ->get()
            ->mapWithKeys(fn (Appointment $a): array => [
                $a->id => $a->inicio->timezone($tz)->format('d/m/Y H:i') . ' · ' . FichaDoPaciente::rotulo($a->tipo) . ' · ' . FichaDoPaciente::rotulo($a->status),
            ])
            ->all();
    }
}
