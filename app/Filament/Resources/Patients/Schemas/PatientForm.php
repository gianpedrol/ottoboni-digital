<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\OrigemPaciente;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\Pacientes\SincronizadorKommo;
use App\Support\CatalogoClinica;
use App\Support\Cpf;
use App\Support\Telefone;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\Auth;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dados pessoais')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome completo')
                            ->required()
                            ->maxLength(160)
                            ->columnSpanFull(),
                        TextInput::make('telefone')
                            ->label('Telefone (WhatsApp)')
                            ->tel()
                            ->required()
                            ->placeholder('(41) 99999-8888')
                            ->mask(RawJs::make(<<<'JS'
                                $input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'
                            JS))
                            ->rules([
                                fn (?Patient $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (Telefone::normalizar((string) $value) === null) {
                                        $fail('Telefone inválido: informe DDD + número.');

                                        return;
                                    }

                                    if (app(SincronizadorKommo::class)->buscarDuplicado((string) $value, $record?->id) !== null) {
                                        $fail('Já existe um paciente com este telefone. Procure-o na lista antes de cadastrar de novo.');
                                    }
                                },
                            ])
                            ->helperText('Usado para achar o contato no Kommo e evitar cadastro duplicado.'),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(160),
                        TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->placeholder('000.000.000-00')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (filled($value) && ! Cpf::valido((string) $value)) {
                                        $fail('CPF inválido: confira os dígitos.');
                                    }
                                },
                            ])
                            ->helperText('Guardado cifrado. Fora deste formulário aparece só mascarado.'),
                        DatePicker::make('data_nascimento')
                            ->label('Data de nascimento')
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()),
                        Select::make('sexo')
                            ->label('Sexo')
                            ->options(Patient::SEXOS),
                    ]),

                Section::make('Atendimento')
                    ->columns(2)
                    ->schema([
                        Select::make('doctor_id')
                            ->label('Médico')
                            ->options(fn (): array => self::medicosPermitidos())
                            ->required()
                            ->live(),
                        TextInput::make('procedimento_interesse')
                            ->label('Procedimento de interesse')
                            ->maxLength(120)
                            ->datalist(fn (Get $get): array => CatalogoClinica::procedimentos(
                                filled($get('doctor_id')) ? Doctor::query()->find($get('doctor_id')) : null,
                            ))
                            ->helperText('Sugestões conforme o médico; pode digitar outro.'),
                        Select::make('origem')
                            ->label('Origem')
                            ->options(OrigemPaciente::class),
                        TextInput::make('indicacao')
                            ->label('Indicação')
                            ->maxLength(120)
                            ->helperText('Quem indicou, se houver.'),
                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Kommo')
                    ->schema([
                        Checkbox::make('abrir_atendimento')
                            ->label('Abrir atendimento no Kommo')
                            ->default(false)
                            ->visible(fn (?Patient $record): bool => $record?->kommo_lead_id === null)
                            ->helperText('Cria um lead no funil do médico, na etapa de entrada, ligado ao contato. No protótipo é só simulado: o painel mostra o que seria enviado.'),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function medicosPermitidos(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        return Doctor::query()
            ->where('ativo', true)
            ->when($user, fn ($q) => $q->whereIn('kommo_pipeline_id', $user->allowedPipelineIds()))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }
}
