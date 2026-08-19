<?php

namespace App\Filament\Pages;

use App\Exports\ArrayExport;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use App\Repositories\LeadFilters;
use App\Repositories\LeadRepository;
use App\Services\Kommo\DTO\PipelineData;
use App\Services\Kommo\KommoException;
use App\Support\PeriodoAtalho;
use App\Support\SelecaoMedico;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class Atendimentos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Atendimento';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Atendimentos';

    protected string $view = 'filament.pages.atendimentos';

    public ?string $dataDe = null;

    /** @var Collection<int, Doctor>|null */
    private ?Collection $doctorsCache = null;

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                array $filters,
                int|string $page,
                int|string $recordsPerPage,
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
            ): LengthAwarePaginator {
                return $this->carregarLeads($filters, (int) $page, $recordsPerPage, $search, $sortColumn, $sortDirection);
            })
            ->resolveSelectedRecordsUsing(function (array $keys, bool $isTrackingDeselectedKeys, array $deselectedKeys): Collection {
                $leads = $this->todosOsLeadsFiltrados();

                return $isTrackingDeselectedKeys
                    ? $leads->reject(fn (array $row): bool => in_array((string) $row['id'], $deselectedKeys, true))
                    : $leads->filter(fn (array $row): bool => in_array((string) $row['id'], $keys, true));
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Nome / @')
                    ->description(fn (array $record): ?string => $record['instagram'])
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('medico')
                    ->label('Médico'),
                TextColumn::make('etapa')
                    ->label('Etapa')
                    ->badge(),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->placeholder('—'),
                TextColumn::make('temperatura')
                    ->label('Temperatura')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtolower((string) $state)) {
                        'quente' => 'danger',
                        'morno' => 'warning',
                        'frio' => 'info',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('procedimento')
                    ->label('Procedimento')
                    ->limit(30)
                    ->placeholder('—'),
                TextColumn::make('score')
                    ->label('Score')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('responsavel')
                    ->label('Responsável')
                    ->placeholder('—'),
                IconColumn::make('gerado_pela_agente')
                    ->label('IA')
                    ->boolean()
                    ->tooltip('Lead gerado pela agente de IA'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i', timezone: config('painel.timezone'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Última atividade')
                    ->since(timezone: config('painel.timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('medico')
                    ->label('Médico / Agente')
                    ->options(fn (): array => SelecaoMedico::options($this->usuario())),
                Filter::make('periodo')
                    ->schema([
                        Select::make('atalho')
                            ->label('Período')
                            ->options(PeriodoAtalho::options())
                            ->default('30d')
                            ->live(),
                        DatePicker::make('de')
                            ->label('De')
                            ->visible(fn ($get): bool => $get('atalho') === 'personalizado'),
                        DatePicker::make('ate')
                            ->label('Até')
                            ->visible(fn ($get): bool => $get('atalho') === 'personalizado'),
                    ]),
                SelectFilter::make('origem')
                    ->label('Origem do lead')
                    ->options([
                        'Tráfego pago' => 'Tráfego pago',
                        'Orgânico-IA' => 'Orgânico-IA',
                    ]),
                SelectFilter::make('temperatura')
                    ->label('Temperatura')
                    ->options([
                        'Quente' => 'Quente',
                        'Morno' => 'Morno',
                        'Frio' => 'Frio',
                    ]),
                Filter::make('procedimento')
                    ->schema([
                        TextInput::make('valor')
                            ->label('Procedimento'),
                    ]),
                SelectFilter::make('etapa')
                    ->label('Etapa do funil')
                    ->options(fn (): array => $this->opcoesDeEtapa()),
                TernaryFilter::make('gerado_pela_agente')
                    ->label('Gerado pela agente')
                    ->placeholder('Todos')
                    ->trueLabel('Só gerados pela agente')
                    ->falseLabel('Só cadastrados à mão'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('atualizar')
                    ->label('Atualizar agora')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (): void {
                        app(LeadRepository::class)->invalidate($this->filtrosAtuais($this->getTableSearch()));

                        Notification::make()
                            ->success()
                            ->title('Dados atualizados')
                            ->body('A próxima consulta buscará direto do Kommo.')
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('ficha')
                    ->label('Ficha')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->url(fn (array $record): string => FichaAtendimento::getUrl(['leadId' => $record['id']])),
                Action::make('kommo')
                    ->label('Abrir no Kommo')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (array $record): string => $record['kommo_url'])
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportar')
                        ->label('Exportar seleção')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn (): bool => $this->podeExportar())
                        ->schema([
                            Select::make('formato')
                                ->label('Formato')
                                ->options(['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV'])
                                ->default('xlsx')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            return $this->exportar($records, $data['formato']);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->paginationPageOptions([10, 25, 50, 100])
            ->emptyStateHeading('Nenhum atendimento no período')
            ->emptyStateDescription('Ajuste o período ou os filtros. Se acabou de configurar o token do Kommo, clique em "Atualizar agora".');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function carregarLeads(
        array $filters,
        int $page,
        int|string $recordsPerPage,
        ?string $search,
        ?string $sortColumn,
        ?string $sortDirection,
    ): LengthAwarePaginator {
        $leadFilters = $this->montarFiltros($filters, $search);

        $perPage = $recordsPerPage === 'all' ? 10000 : (int) $recordsPerPage;

        try {
            $paginado = app(LeadRepository::class)->paginateLeads(
                $leadFilters,
                $page,
                $perPage,
                $sortColumn,
                $sortDirection,
            );
        } catch (KommoException $e) {
            Notification::make()
                ->danger()
                ->title('Falha ao consultar o Kommo')
                ->body($e->getMessage())
                ->send();

            return new Paginator([], 0, $perPage, $page);
        }

        $this->dataDe = app(LeadRepository::class)
            ->fetchedAt($leadFilters)
            ?->setTimezone(config('painel.timezone'))
            ->format('d/m/Y H:i');

        $items = collect($paginado->items())
            ->map(fn (array $row): array => $this->enriquecer($row));

        return new Paginator(
            items: $items,
            total: $paginado->total(),
            perPage: $paginado->perPage(),
            currentPage: $paginado->currentPage(),
        );
    }

    /**
     * @param  array<string, mixed>  $filters  estado cru dos filtros da tabela
     */
    private function montarFiltros(array $filters, ?string $search): LeadFilters
    {
        [$de, $ate] = PeriodoAtalho::resolver(
            $filters['periodo']['atalho'] ?? '30d',
            $filters['periodo']['de'] ?? null,
            $filters['periodo']['ate'] ?? null,
        );

        $geradoPelaAgente = $filters['gerado_pela_agente']['value'] ?? null;

        return new LeadFilters(
            pipelineIds: SelecaoMedico::pipelineIds($this->usuario(), $filters['medico']['value'] ?? null),
            from: $de->utc(),
            to: $ate->utc(),
            search: $search,
            origem: $filters['origem']['value'] ?? null,
            temperatura: $filters['temperatura']['value'] ?? null,
            procedimento: filled($filters['procedimento']['valor'] ?? null) ? $filters['procedimento']['valor'] : null,
            statusId: filled($filters['etapa']['value'] ?? null) ? (int) $filters['etapa']['value'] : null,
            geradoPelaAgente: $geradoPelaAgente === null ? null : (bool) $geradoPelaAgente,
        );
    }

    private function filtrosAtuais(?string $search): LeadFilters
    {
        return $this->montarFiltros($this->tableFilters ?? [], $search);
    }

    /**
     * Conjunto filtrado completo (para seleção e exportação).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function todosOsLeadsFiltrados(): Collection
    {
        return app(LeadRepository::class)
            ->leads($this->filtrosAtuais($this->getTableSearch()))
            ->map(fn ($lead): array => $this->enriquecer($lead->toRow()));
    }

    /**
     * Completa a linha com rótulos legíveis (médico, etapa, responsável).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enriquecer(array $row): array
    {
        $this->doctorsCache ??= Doctor::query()->get()->keyBy('kommo_pipeline_id');

        $doctor = $this->doctorsCache->get($row['pipeline_id']);
        $row['medico'] = $doctor !== null ? $doctor->nome : "Pipeline {$row['pipeline_id']}";

        $row['etapa'] = $this->pipelinesKommo()
            ->first(fn (PipelineData $p): bool => $p->id === $row['pipeline_id'])
            ?->statusName($row['status_id']) ?? "Etapa {$row['status_id']}";

        $row['responsavel'] = $this->usuariosKommo()->get($row['responsible_user_id'])?->name;

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function opcoesDeEtapa(): array
    {
        $medico = $this->tableFilters['medico']['value'] ?? null;
        $pipelineIds = SelecaoMedico::pipelineIds($this->usuario(), $medico);

        $options = [];

        foreach ($this->pipelinesKommo() as $pipeline) {
            if (! in_array($pipeline->id, $pipelineIds, true)) {
                continue;
            }

            $sufixo = count($pipelineIds) > 1 ? " ({$pipeline->name})" : '';

            foreach ($pipeline->statuses as $status) {
                $options[$status->id] = $status->name . $sufixo;
            }
        }

        return $options;
    }

    /**
     * @return Collection<int, PipelineData>
     */
    private function pipelinesKommo(): Collection
    {
        try {
            return app(LeadRepository::class)->pipelines();
        } catch (KommoException) {
            return collect();
        }
    }

    /**
     * @return Collection<int, \App\Services\Kommo\DTO\KommoUserData>
     */
    private function usuariosKommo(): Collection
    {
        try {
            return app(LeadRepository::class)->users()->keyBy('id');
        } catch (KommoException) {
            return collect();
        }
    }

    private function podeExportar(): bool
    {
        $user = $this->usuario();

        return $user->isAdmin() || $user->isGestor();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     */
    private function exportar(Collection $records, string $formato): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        AuditLog::registrar('exportou_atendimentos', [
            'quantidade' => $records->count(),
            'formato' => $formato,
            'filtros' => $this->tableFilters,
        ]);

        $colunas = [
            'id' => 'ID',
            'name' => 'Nome',
            'instagram' => 'Instagram',
            'medico' => 'Médico',
            'etapa' => 'Etapa',
            'origem' => 'Origem',
            'temperatura' => 'Temperatura',
            'procedimento' => 'Procedimento',
            'score' => 'Score',
            'responsavel' => 'Responsável',
            'created_at' => 'Criado em',
        ];

        $rows = $records
            ->map(function (array $row) use ($colunas): array {
                $out = [];

                foreach (array_keys($colunas) as $key) {
                    $value = $row[$key] ?? null;

                    $out[$key] = $value instanceof \Carbon\CarbonInterface
                        ? $value->setTimezone(config('painel.timezone'))->format('d/m/Y H:i')
                        : $value;
                }

                return $out;
            })
            ->values()
            ->all();

        $arquivo = 'atendimentos-' . now(config('painel.timezone'))->format('Y-m-d-Hi') . '.' . $formato;

        return Excel::download(
            new ArrayExport($rows, array_values($colunas)),
            $arquivo,
            $formato === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
