<?php

namespace App\Filament\Pages;

use App\Enums\StatusAgendamento;
use App\Enums\TipoAgendamento;
use App\Filament\Resources\Appointments\Actions\AcoesDoAgendamento;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\AvisoDaAgenda;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\ConflitoDeHorario;
use App\Support\Demonstracao;
use App\Support\Telefone;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Agenda própria da clínica: visão semana (grade seg–sáb) e visão dia
 * (lista da recepção com ações rápidas).
 */
class Agenda extends Page implements HasTable
{
    use InteractsWithTable;

    public const INICIO_DIA = 7 * 60;

    public const FIM_DIA = 20 * 60;

    public const SLOT_MIN = 30;

    public const ALTURA_SLOT = 44;

    private const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Clínica';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Agenda';

    protected string $view = 'filament.pages.agenda';

    #[Url]
    public string $visao = 'semana';

    #[Url]
    public ?string $data = null;

    #[Url]
    public string $medico = 'ambos';

    public bool $mostrarCancelados = false;

    /** @var Collection<int, Doctor>|null */
    private ?Collection $medicosPermitidosCache = null;

    /** @var array<int, Appointment|null> */
    private array $agendamentosCache = [];

    public function mount(): void
    {
        $this->data = $this->dataAtual()->format('Y-m-d');

        if (! in_array($this->visao, ['semana', 'dia'], true)) {
            $this->visao = 'semana';
        }

        $opcoes = $this->opcoesDeMedico();

        if (! array_key_exists($this->medico, $opcoes)) {
            $this->medico = (string) array_key_first($opcoes);
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lista')
                ->label('Lista de agendamentos')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('gray')
                ->url(AppointmentResource::getUrl('index')),
            $this->novoAction(),
        ];
    }

    // --- navegação -------------------------------------------------------

    public function anterior(): void
    {
        $this->mover(-1);
    }

    public function proximo(): void
    {
        $this->mover(1);
    }

    public function hoje(): void
    {
        $this->data = CarbonImmutable::now(Appointment::fuso())->format('Y-m-d');
    }

    public function irPara(string $data): void
    {
        $this->data = $data;
        $this->data = $this->dataAtual()->format('Y-m-d');
        $this->visao = 'dia';
    }

    private function mover(int $direcao): void
    {
        $data = $this->dataAtual();

        if ($this->visao === 'dia') {
            $data = $data->addDays($direcao);

            if ($data->isSunday()) {
                $data = $data->addDays($direcao);
            }
        } else {
            $data = $data->addWeeks($direcao);
        }

        $this->data = $data->format('Y-m-d');
    }

    public function dataAtual(): CarbonImmutable
    {
        $fuso = Appointment::fuso();

        try {
            return filled($this->data)
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->data, $fuso)->startOfDay()
                : CarbonImmutable::now($fuso)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::now($fuso)->startOfDay();
        }
    }

    // --- médicos (sempre dentro do escopo do usuário) ---------------------

    /**
     * @return array<string, string>
     */
    public function opcoesDeMedico(): array
    {
        $opcoes = $this->medicosPermitidos()
            ->mapWithKeys(fn (Doctor $d): array => [$d->agente => $d->nome])
            ->all();

        if (count($opcoes) > 1) {
            $opcoes = ['ambos' => 'Os dois, lado a lado'] + $opcoes;
        }

        return $opcoes;
    }

    /**
     * @return Collection<int, Doctor>
     */
    private function medicosPermitidos(): Collection
    {
        return $this->medicosPermitidosCache ??= Doctor::query()
            ->where('ativo', true)
            ->whereIn('kommo_pipeline_id', $this->usuario()->allowedPipelineIds())
            ->orderBy('id')
            ->get();
    }

    /**
     * Pediu médico fora do escopo (URL manipulada)? Recebe só o que pode ver.
     *
     * @return Collection<int, Doctor>
     */
    public function medicosVisiveis(): Collection
    {
        $permitidos = $this->medicosPermitidos();
        $escolhido = $permitidos->where('agente', $this->medico)->values();

        return $escolhido->isNotEmpty() ? $escolhido : $permitidos;
    }

    public static function corDoMedico(Doctor $medico): string
    {
        return match ($medico->agente) {
            'duda' => 'teal',
            'luna' => 'rose',
            default => 'slate',
        };
    }

    public static function apelido(Doctor $medico): string
    {
        $nome = preg_replace('/^Dra?\.?\s+/u', '', (string) $medico->nome) ?? (string) $medico->nome;

        return explode(' ', $nome)[0];
    }

    // --- dados da tela ----------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'opcoesMedico' => $this->opcoesDeMedico(),
            'periodo' => $this->rotuloPeriodo(),
            'semana' => $this->visao === 'dia' ? null : $this->montarSemana(),
            'resumoDia' => $this->visao === 'dia' ? $this->resumoDoDia() : null,
            'alturaSlot' => self::ALTURA_SLOT,
        ];
    }

    private function rotuloPeriodo(): string
    {
        $data = $this->dataAtual()->locale('pt_BR');

        if ($this->visao === 'dia') {
            return ucfirst($data->translatedFormat('l, d \d\e F \d\e Y'));
        }

        $segunda = $data->startOfWeek(CarbonInterface::MONDAY);
        $sabado = $segunda->addDays(5);

        return $segunda->month === $sabado->month
            ? $segunda->format('d') . ' a ' . $sabado->translatedFormat('d \d\e F \d\e Y')
            : $segunda->translatedFormat('d \d\e F') . ' a ' . $sabado->translatedFormat('d \d\e F \d\e Y');
    }

    /**
     * @return array<string, mixed>
     */
    private function montarSemana(): array
    {
        $fuso = Appointment::fuso();
        $agora = CarbonImmutable::now($fuso);
        $segunda = $this->dataAtual()->startOfWeek(CarbonInterface::MONDAY);
        $medicos = $this->medicosVisiveis();

        $agendamentos = $this->consultaBase()
            ->entre($segunda, $segunda->addDays(6))
            ->with(['patient:id,nome', 'service:id,nome'])
            ->orderBy('inicio')
            ->get();

        $dias = [];

        for ($i = 0; $i < 6; $i++) {
            $dia = $segunda->addDays($i);
            $colunas = [];
            $total = 0;

            foreach ($medicos as $medico) {
                $doDia = $agendamentos->filter(
                    fn (Appointment $a): bool => $a->doctor_id === $medico->id && $a->inicioLocal()->isSameDay($dia),
                );

                $total += $doDia->filter(fn (Appointment $a): bool => ! in_array($a->status, StatusAgendamento::liberamHorario(), true))->count();

                $colunas[] = [
                    'medico' => $medico,
                    'apelido' => self::apelido($medico),
                    'cor' => self::corDoMedico($medico),
                    'eventos' => $this->posicionar($doDia),
                ];
            }

            $dias[] = [
                'data' => $dia->format('Y-m-d'),
                'semana' => self::DIAS[$i],
                'numero' => $dia->format('d'),
                'mes' => $dia->format('m'),
                'hoje' => $dia->isSameDay($agora),
                'passado' => $dia->lt($agora->startOfDay()),
                'total' => $total,
                'colunas' => $colunas,
            ];
        }

        $slots = [];

        for ($min = self::INICIO_DIA; $min < self::FIM_DIA; $min += self::SLOT_MIN) {
            $slots[] = [
                'hora' => sprintf('%02d:%02d', intdiv($min, 60), $min % 60),
                'top' => $this->pixels($min),
                'cheia' => $min % 60 === 0,
            ];
        }

        $minutosAgora = $agora->hour * 60 + $agora->minute;
        $semanaAtual = $agora->betweenIncluded($segunda, $segunda->addDays(6));

        return [
            'dias' => $dias,
            'slots' => $slots,
            'altura' => $this->pixels(self::FIM_DIA),
            'ladoALado' => $medicos->count() > 1,
            'medicos' => $medicos->map(fn (Doctor $m): array => ['nome' => $m->nome, 'cor' => self::corDoMedico($m)])->all(),
            'linhaAgora' => $semanaAtual && $minutosAgora >= self::INICIO_DIA && $minutosAgora <= self::FIM_DIA
                ? $this->pixels($minutosAgora)
                : null,
        ];
    }

    /**
     * Posição de cada bloco na coluna. Blocos que se cruzam (só acontece
     * com cancelados/remarcados à mostra) dividem a largura.
     *
     * @param  \Illuminate\Support\Collection<int, Appointment>  $agendamentos
     * @return array<int, array<string, mixed>>
     */
    private function posicionar(\Illuminate\Support\Collection $agendamentos): array
    {
        $saida = [];
        $grupo = [];
        $fimDoGrupo = -1;
        $faixas = [];

        foreach ($agendamentos->sortBy('inicio') as $a) {
            $inicio = $a->inicioLocal();
            $fim = $a->fimLocal();

            $ini = max(self::INICIO_DIA, $inicio->hour * 60 + $inicio->minute);
            $fimMin = $fim->isSameDay($inicio) ? $fim->hour * 60 + $fim->minute : self::FIM_DIA;
            $fimMin = min(self::FIM_DIA, max($fimMin, $ini + 15));

            if ($ini >= self::FIM_DIA) {
                continue;
            }

            if ($grupo !== [] && $ini >= $fimDoGrupo) {
                array_push($saida, ...$this->distribuir($grupo, count($faixas)));
                $grupo = [];
                $faixas = [];
                $fimDoGrupo = -1;
            }

            $faixa = 0;

            while (isset($faixas[$faixa]) && $faixas[$faixa] > $ini) {
                $faixa++;
            }

            $faixas[$faixa] = $fimMin;
            $fimDoGrupo = max($fimDoGrupo, $fimMin);

            $altura = max(22, $this->pixels($fimMin) - $this->pixels($ini) - 2);

            $grupo[] = [
                'id' => $a->id,
                'top' => $this->pixels($ini) + 1,
                'altura' => $altura,
                'faixa' => $faixa,
                'paciente' => $a->patient->nome ?? 'Paciente',
                'horario' => $a->faixaHorario(),
                'tipo' => $a->tipo,
                'status' => $a->status,
                'servico' => $a->service?->nome,
                'sala' => $a->sala,
                'compacto' => $altura < 40,
            ];
        }

        if ($grupo !== []) {
            array_push($saida, ...$this->distribuir($grupo, count($faixas)));
        }

        return $saida;
    }

    /**
     * Divide a largura da coluna entre os blocos de um grupo sobreposto.
     *
     * @param  array<int, array<string, mixed>>  $grupo
     * @return array<int, array<string, mixed>>
     */
    private function distribuir(array $grupo, int $colunas): array
    {
        $colunas = max(1, $colunas);

        return array_map(fn (array $item): array => [
            ...$item,
            'esquerda' => $item['faixa'] / $colunas * 100,
            'largura' => 100 / $colunas,
        ], $grupo);
    }

    private function pixels(int $minutosDoDia): int
    {
        return (int) round(($minutosDoDia - self::INICIO_DIA) / self::SLOT_MIN * self::ALTURA_SLOT);
    }

    /**
     * @return array<string, int>
     */
    private function resumoDoDia(): array
    {
        $dia = $this->dataAtual();

        $status = Appointment::query()
            ->visivelPara($this->usuario())
            ->whereIn('doctor_id', $this->medicosVisiveis()->modelKeys())
            ->entre($dia, $dia->addDay())
            ->pluck('status');

        $conta = fn (StatusAgendamento $s): int => $status->filter(fn ($v): bool => $v === $s)->count();

        return [
            'agendamentos' => $status->reject(fn ($v): bool => in_array($v, StatusAgendamento::liberamHorario(), true))->count(),
            'confirmados' => $conta(StatusAgendamento::Confirmado),
            'realizados' => $conta(StatusAgendamento::Realizado),
            'faltas' => $conta(StatusAgendamento::Faltou),
            'cancelados' => $conta(StatusAgendamento::Cancelado) + $conta(StatusAgendamento::Remarcado),
        ];
    }

    /**
     * @return Builder<Appointment>
     */
    private function consultaBase(): Builder
    {
        return Appointment::query()
            ->visivelPara($this->usuario())
            ->whereIn('doctor_id', $this->medicosVisiveis()->modelKeys())
            ->when(! $this->mostrarCancelados, fn (Builder $q) => $q->ocupandoHorario());
    }

    // --- visão dia (recepção) ---------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $dia = $this->dataAtual();

                return $this->consultaBase()
                    ->entre($dia, $dia->addDay())
                    ->with(['patient', 'doctor', 'service']);
            })
            ->columns([
                TextColumn::make('inicio')
                    ->label('Horário')
                    ->formatStateUsing(fn (Appointment $record): string => $record->faixaHorario())
                    ->weight('bold'),
                TextColumn::make('patient.nome')
                    ->label('Paciente')
                    ->weight('medium')
                    ->description(fn (Appointment $record): ?string => Telefone::paraMascara($record->patient?->telefone)),
                TextColumn::make('doctor.nome')
                    ->label('Médico')
                    ->visible(fn (): bool => $this->medicosVisiveis()->count() > 1),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('service.nome')
                    ->label('Serviço')
                    ->limit(28)
                    ->placeholder('—'),
                TextColumn::make('sala')
                    ->label('Sala')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                AcoesDoAgendamento::confirmar(),
                AcoesDoAgendamento::realizado(),
                AcoesDoAgendamento::faltou(),
                ActionGroup::make([
                    AcoesDoAgendamento::remarcar(),
                    AcoesDoAgendamento::cancelar(),
                    Action::make('editar')
                        ->label('Editar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->url(fn (Appointment $record): string => AppointmentResource::getUrl('edit', ['record' => $record])),
                    Action::make('paciente')
                        ->label('Abrir paciente / prontuário')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->visible(fn (Appointment $record): bool => AvisoDaAgenda::urlPaciente($record->patient_id) !== null)
                        ->url(fn (Appointment $record): ?string => AvisoDaAgenda::urlPaciente($record->patient_id)),
                ])->tooltip('Mais ações'),
            ])
            ->recordClasses(fn (Appointment $record): ?string => in_array($record->status, StatusAgendamento::liberamHorario(), true) ? 'opacity-60' : null)
            ->defaultSort('inicio')
            ->paginated(false)
            ->emptyStateHeading('Nenhum agendamento neste dia')
            ->emptyStateDescription('Use "Novo agendamento" ou clique num horário livre na visão semana.');
    }

    // --- ações da página (modais da visão semana) --------------------------

    public function novoAction(): Action
    {
        return Action::make('novo')
            ->label('Novo agendamento')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Novo agendamento')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Agendar')
            ->schema([
                Grid::make(2)->schema(AppointmentForm::componentes()),
            ])
            ->fillForm(function (array $arguments): array {
                $visiveis = $this->medicosVisiveis();

                return [
                    'data' => $arguments['data'] ?? $this->dataAtual()->format('Y-m-d'),
                    'hora' => $arguments['hora'] ?? null,
                    'doctor_id' => $arguments['medico'] ?? ($visiveis->count() === 1 ? $visiveis->first()?->id : null),
                    'tipo' => TipoAgendamento::Consulta->value,
                    'duracao_min' => 30,
                ];
            })
            ->action(function (array $data, Action $action): void {
                try {
                    $resultado = app(AgendaService::class)->agendar(AppointmentForm::paraServico($data));
                } catch (ConflitoDeHorario|DomainException $e) {
                    Notification::make()->danger()->title('Não foi possível agendar')->body(e($e->getMessage()))->send();
                    $action->halt();

                    return;
                }

                AvisoDaAgenda::enviar($resultado, 'Agendamento criado');
            });
    }

    public function detalheAction(): Action
    {
        return Action::make('detalhe')
            ->record(fn (array $arguments): ?Appointment => $this->agendamento($arguments['id'] ?? null))
            ->visible(fn (?Appointment $record): bool => $record !== null)
            ->modalHeading(fn (?Appointment $record): string => $record->patient->nome ?? 'Agendamento')
            ->modalContent(fn (?Appointment $record) => $record !== null
                ? view('filament.agenda.detalhe', ['agendamento' => $record])
                : null)
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->extraModalFooterActions(fn (Action $action, ?Appointment $record): array => $record !== null
                ? $this->botoesDoDetalhe($action, $record)
                : [])
            ->action(function (array $arguments, ?Appointment $record): void {
                $op = $arguments['op'] ?? null;

                if ($record !== null && in_array($op, ['confirmar', 'realizado', 'faltou', 'remarcar', 'cancelar'], true)) {
                    // Troca o modal de detalhe pela ação escolhida (confirmação ou formulário).
                    $this->replaceMountedAction($op, ['id' => $record->getKey()]);
                }
            });
    }

    public function confirmarAction(): Action
    {
        return $this->doArgumento(AcoesDoAgendamento::confirmar());
    }

    public function realizadoAction(): Action
    {
        return $this->doArgumento(AcoesDoAgendamento::realizado());
    }

    public function faltouAction(): Action
    {
        return $this->doArgumento(AcoesDoAgendamento::faltou());
    }

    public function remarcarAction(): Action
    {
        return $this->doArgumento(AcoesDoAgendamento::remarcar());
    }

    public function cancelarAction(): Action
    {
        return $this->doArgumento(AcoesDoAgendamento::cancelar());
    }

    /**
     * @return array<int, Action>
     */
    private function botoesDoDetalhe(Action $action, Appointment $record): array
    {
        $botoes = [];

        $op = fn (string $nome, string $rotulo, Heroicon $icone, string $cor): Action => $action
            ->makeModalSubmitAction($nome, ['op' => $nome])
            ->label($rotulo)
            ->icon($icone)
            ->color($cor);

        if ($record->status === StatusAgendamento::Agendado) {
            $botoes[] = $op('confirmar', 'Confirmar', Heroicon::OutlinedCheckCircle, 'success');
        }

        if (AcoesDoAgendamento::podeMarcarComparecimento($record)) {
            $botoes[] = $op('realizado', 'Chegou / realizado', Heroicon::OutlinedCheckBadge, 'primary');
            $botoes[] = $op('faltou', 'Faltou', Heroicon::OutlinedXCircle, 'danger');
        }

        if ($record->status->emAberto()) {
            $botoes[] = $op('remarcar', 'Remarcar', Heroicon::OutlinedArrowPath, 'warning');
            $botoes[] = $op('cancelar', 'Cancelar', Heroicon::OutlinedNoSymbol, 'gray');
        }

        $botoes[] = Action::make('editar')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->button()
            ->url(AppointmentResource::getUrl('edit', ['record' => $record]));

        $urlPaciente = AvisoDaAgenda::urlPaciente($record->patient_id);

        if ($urlPaciente !== null) {
            $botoes[] = Action::make('paciente')
                ->label('Abrir paciente / prontuário')
                ->icon(Heroicon::OutlinedUserCircle)
                ->color('gray')
                ->button()
                ->url($urlPaciente);
        }

        return $botoes;
    }

    private function doArgumento(Action $action): Action
    {
        return $action->record(fn (array $arguments): ?Appointment => $this->agendamento($arguments['id'] ?? null));
    }

    /**
     * Agendamento pelo id, sempre dentro do escopo do usuário.
     */
    private function agendamento(mixed $id): ?Appointment
    {
        if (blank($id) || ! is_numeric($id)) {
            return null;
        }

        $id = (int) $id;

        if (! array_key_exists($id, $this->agendamentosCache)) {
            $this->agendamentosCache[$id] = Appointment::query()
                ->visivelPara($this->usuario())
                ->with(['patient', 'doctor', 'service'])
                ->find($id);
        }

        return $this->agendamentosCache[$id];
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
