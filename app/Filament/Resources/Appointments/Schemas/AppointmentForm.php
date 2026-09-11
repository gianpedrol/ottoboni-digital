<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\TipoAgendamento;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\ConflitoDeHorario;
use App\Support\Telefone;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * O formulário trabalha com data + hora + duração no fuso da clínica e
 * converte para inicio/fim só na hora de salvar (paraServico).
 */
class AppointmentForm
{
    public const SALAS = [
        'Consultório 1',
        'Consultório 2',
        'Sala de procedimentos',
        'Sala de estética',
        'Centro cirúrgico',
        'Online',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Agendamento')
                    ->schema(self::componentes())
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Também usado no modal "novo agendamento" da página Agenda.
     *
     * @return array<int, Component|\Filament\Forms\Components\Field>
     */
    public static function componentes(): array
    {
        return [
            Select::make('patient_id')
                ->label('Paciente')
                ->searchable()
                ->required()
                ->options(fn (): array => self::buscarPacientes(''))
                ->getSearchResultsUsing(fn (string $search): array => self::buscarPacientes($search))
                ->getOptionLabelUsing(fn ($value): ?string => self::rotuloPaciente($value))
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (self::rotuloPaciente($value) === null) {
                        $fail('Paciente não encontrado.');
                    }
                }])
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    if (filled($get('doctor_id'))) {
                        return;
                    }

                    $doctorId = Patient::query()->whereKey($state)->value('doctor_id');

                    if ($doctorId !== null && array_key_exists($doctorId, self::medicosPermitidos())) {
                        $set('doctor_id', $doctorId);
                    }
                })
                ->columnSpanFull(),
            Select::make('doctor_id')
                ->label('Médico')
                ->options(fn (): array => self::medicosPermitidos())
                ->in(fn (): array => array_keys(self::medicosPermitidos()))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('service_id', null)),
            Select::make('service_id')
                ->label('Serviço')
                ->options(fn (Get $get): array => self::servicosDoMedico($get('doctor_id')))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    $servico = Service::query()->find($state);

                    if ($servico === null) {
                        return;
                    }

                    $set('duracao_min', $servico->duracao_min);

                    $tipo = TipoAgendamento::doServico($servico->tipo);
                    $atual = self::tipo($get('tipo'));

                    // Consulta online continua online mesmo escolhendo o serviço "consulta".
                    if ($tipo !== null && ! ($atual === TipoAgendamento::Online && $tipo === TipoAgendamento::Consulta)) {
                        $set('tipo', $tipo->value);
                        self::sugerirSala($tipo, $get, $set);
                    }
                })
                ->helperText('Só os serviços do médico escolhido. Preenche a duração.'),
            Select::make('tipo')
                ->label('Tipo')
                ->options(TipoAgendamento::class)
                ->default(TipoAgendamento::Consulta->value)
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::sugerirSala(self::tipo($state), $get, $set)),
            DatePicker::make('data')
                ->label('Data')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->firstDayOfWeek(1)
                ->required()
                ->live(),
            Select::make('hora')
                ->label('Início')
                ->options(self::horarios())
                ->required()
                ->live()
                ->rules([self::regraDeConflito()]),
            TextInput::make('duracao_min')
                ->label('Duração')
                ->numeric()
                ->integer()
                ->minValue(10)
                ->maxValue(720)
                ->step(5)
                ->suffix('min')
                ->default(30)
                ->required()
                ->live(onBlur: true)
                ->helperText(fn (Get $get): ?string => self::textoTermino($get('data'), $get('hora'), $get('duracao_min'))),
            TextInput::make('sala')
                ->label('Sala')
                ->datalist(self::SALAS)
                ->maxLength(60),
            Textarea::make('observacoes')
                ->label('Observações')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * Regra de validação: o médico não pode ter outro agendamento no mesmo
     * intervalo (cancelados e remarcados não contam).
     *
     * @param  int|null  $doctorIdFixo  quando o médico não é campo do formulário (remarcar)
     */
    public static function regraDeConflito(?int $doctorIdFixo = null, ?int $ignorarId = null): Closure
    {
        return fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $doctorIdFixo, $ignorarId): void {
            $doctorId = $doctorIdFixo ?? (filled($get('doctor_id')) ? (int) $get('doctor_id') : null);
            $intervalo = self::intervalo($get('data'), $value, $get('duracao_min'));

            if ($doctorId === null || $intervalo === null) {
                return;
            }

            $ignorar = $ignorarId ?? ($record instanceof Appointment ? (int) $record->getKey() : null);

            $existente = app(AgendaService::class)
                ->conflitos($doctorId, $intervalo[0], $intervalo[1], $ignorar)
                ->first();

            if ($existente !== null) {
                $fail((new ConflitoDeHorario($existente))->getMessage());
            }
        };
    }

    /**
     * Converte o estado do formulário no formato do AgendaService.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paraServico(array $data): array
    {
        [$inicio, $fim] = self::intervalo($data['data'] ?? null, $data['hora'] ?? null, $data['duracao_min'] ?? null)
            ?? throw new \DomainException('Informe data, horário e duração.');

        unset($data['data'], $data['hora'], $data['duracao_min']);

        return [
            ...$data,
            'inicio' => $inicio,
            'fim' => $fim,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function doAgendamento(Appointment $agendamento): array
    {
        return [
            'data' => $agendamento->inicioLocal()->format('Y-m-d'),
            'hora' => $agendamento->inicioLocal()->format('H:i'),
            'duracao_min' => $agendamento->duracaoMin(),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null  no fuso da clínica
     */
    public static function intervalo(mixed $data, mixed $hora, mixed $duracao): ?array
    {
        if (blank($data) || blank($hora) || blank($duracao) || (int) $duracao <= 0) {
            return null;
        }

        try {
            $inicio = CarbonImmutable::parse(substr((string) $data, 0, 10) . ' ' . $hora, Appointment::fuso())->startOfMinute();
        } catch (\Throwable) {
            return null;
        }

        return [$inicio, $inicio->addMinutes((int) $duracao)];
    }

    /**
     * Horários de 15 em 15 minutos, das 07:00 às 19:45.
     *
     * @return array<string, string>
     */
    public static function horarios(): array
    {
        $horarios = [];

        for ($minutos = 7 * 60; $minutos < 20 * 60; $minutos += 15) {
            $hora = sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
            $horarios[$hora] = $hora;
        }

        return $horarios;
    }

    /**
     * Médicos ativos dentro do escopo do usuário.
     *
     * @return array<int, string>
     */
    public static function medicosPermitidos(): array
    {
        return Doctor::query()
            ->where('ativo', true)
            ->whereIn('kommo_pipeline_id', self::usuario()?->allowedPipelineIds() ?? [])
            ->orderBy('id')
            ->pluck('nome', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function servicosDoMedico(mixed $doctorId): array
    {
        if (blank($doctorId)) {
            return [];
        }

        return Service::query()
            ->where('ativo', true)
            ->where(fn ($q) => $q->where('doctor_id', $doctorId)->orWhereNull('doctor_id'))
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Service $s): array => [$s->id => "{$s->nome} · {$s->duracao_min} min"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function buscarPacientes(string $busca): array
    {
        $user = self::usuario();

        if ($user === null) {
            return [];
        }

        $digitos = preg_replace('/\D/', '', $busca) ?? '';

        return Patient::query()
            ->visivelPara($user)
            ->when(filled($busca), fn ($q) => $q->where(function ($q) use ($busca, $digitos): void {
                $q->where('nome', 'like', "%{$busca}%");

                if (strlen($digitos) >= 4) {
                    $q->orWhere('telefone', 'like', "%{$digitos}%");
                }
            }))
            ->orderBy('nome')
            ->limit(30)
            ->get(['id', 'nome', 'telefone'])
            ->mapWithKeys(fn (Patient $p): array => [$p->id => self::formatarPaciente($p)])
            ->all();
    }

    public static function rotuloPaciente(mixed $id): ?string
    {
        $user = self::usuario();

        if (blank($id) || $user === null) {
            return null;
        }

        $paciente = Patient::query()->visivelPara($user)->find($id, ['id', 'nome', 'telefone']);

        return $paciente !== null ? self::formatarPaciente($paciente) : null;
    }

    private static function formatarPaciente(Patient $paciente): string
    {
        $telefone = Telefone::paraMascara($paciente->telefone);

        return $paciente->nome . ($telefone ? " · {$telefone}" : '');
    }

    private static function textoTermino(mixed $data, mixed $hora, mixed $duracao): ?string
    {
        $intervalo = self::intervalo($data, $hora, $duracao);

        return $intervalo !== null ? 'Termina às ' . $intervalo[1]->format('H:i') . '.' : null;
    }

    private static function sugerirSala(?TipoAgendamento $tipo, Get $get, Set $set): void
    {
        $sugestao = match ($tipo) {
            TipoAgendamento::Cirurgia => 'Centro cirúrgico',
            TipoAgendamento::Online => 'Online',
            default => null,
        };

        // Só troca se a sala estiver vazia ou tiver sido sugerida antes.
        if ($sugestao !== null && in_array($get('sala'), [null, '', 'Centro cirúrgico', 'Online'], true)) {
            $set('sala', $sugestao);
        }
    }

    private static function tipo(mixed $state): ?TipoAgendamento
    {
        return $state instanceof TipoAgendamento ? $state : TipoAgendamento::tryFrom((string) $state);
    }

    private static function usuario(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }
}
