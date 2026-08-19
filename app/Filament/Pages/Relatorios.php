<?php

namespace App\Filament\Pages;

use App\Exports\ArrayExport;
use App\Jobs\GerarRelatorioJob;
use App\Models\AuditLog;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\Kommo\KommoException;
use App\Services\Reports\ReportService;
use App\Support\PeriodoAtalho;
use App\Support\SelecaoMedico;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Relatorios extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Atendimento';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Relatórios';

    protected string $view = 'filament.pages.relatorios';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $relatorio = null;

    public function mount(): void
    {
        $this->form->fill([
            'medico' => 'ambos',
            'periodo' => '30d',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Período e médico')
                    ->schema([
                        Select::make('medico')
                            ->label('Médico')
                            ->options(SelecaoMedico::options($this->usuario()))
                            ->required(),
                        Select::make('periodo')
                            ->label('Período')
                            ->options(PeriodoAtalho::options())
                            ->required()
                            ->live(),
                        DatePicker::make('de')
                            ->label('De')
                            ->visible(fn ($get): bool => $get('periodo') === 'personalizado')
                            ->requiredIf('periodo', 'personalizado'),
                        DatePicker::make('ate')
                            ->label('Até')
                            ->visible(fn ($get): bool => $get('periodo') === 'personalizado')
                            ->requiredIf('periodo', 'personalizado'),
                    ])
                    ->columns(4),
            ]);
    }

    public function gerar(): void
    {
        $state = $this->form->getState();

        [$de, $ate] = PeriodoAtalho::resolver($state['periodo'], $state['de'] ?? null, $state['ate'] ?? null);
        $pipelineIds = SelecaoMedico::pipelineIds($this->usuario(), $state['medico']);

        $dias = (int) $de->diffInDays($ate->addSecond());

        AuditLog::registrar('gerou_relatorio', [
            'medico' => $state['medico'],
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'dias' => $dias,
        ]);

        // Período longo vira job: o usuário é avisado quando ficar pronto.
        if ($dias > (int) config('kommo.report_job_threshold_days')) {
            $snapshot = ReportSnapshot::query()->create([
                'tipo' => 'periodo_longo',
                'status' => 'pendente',
                'filtros' => [
                    'pipeline_ids' => $pipelineIds,
                    'de' => $de->toDateString(),
                    'ate' => $ate->toDateString(),
                    'medico' => $state['medico'],
                ],
                'gerado_por' => $this->usuario()->id,
            ]);

            GerarRelatorioJob::dispatch($snapshot->id);

            Notification::make()
                ->info()
                ->title('Relatório em processamento')
                ->body("Período de {$dias} dias é gerado em segundo plano. Você será avisado aqui no painel quando ficar pronto.")
                ->send();

            return;
        }

        try {
            $this->relatorio = app(ReportService::class)->generate($pipelineIds, $de, $ate);
        } catch (KommoException $e) {
            Notification::make()
                ->danger()
                ->title('Falha ao consultar o Kommo')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function abrirSnapshot(int $snapshotId): void
    {
        $snapshot = ReportSnapshot::query()
            ->where('status', 'pronto')
            ->findOrFail($snapshotId);

        // Snapshot também respeita o escopo do usuário.
        $permitidos = $this->usuario()->allowedPipelineIds();
        $doSnapshot = array_map(intval(...), $snapshot->filtros['pipeline_ids'] ?? []);

        if (array_diff($doSnapshot, $permitidos) !== []) {
            abort(403);
        }

        $this->relatorio = $snapshot->dados;
    }

    /**
     * @return array<int, ReportSnapshot>
     */
    public function getSnapshotsProperty(): array
    {
        $permitidos = $this->usuario()->allowedPipelineIds();

        return ReportSnapshot::query()
            ->whereIn('status', ['pendente', 'gerando', 'pronto', 'erro'])
            ->latest()
            ->limit(10)
            ->get()
            ->filter(function (ReportSnapshot $s) use ($permitidos): bool {
                $ids = array_map(intval(...), $s->filtros['pipeline_ids'] ?? []);

                return array_diff($ids, $permitidos) === [];
            })
            ->values()
            ->all();
    }

    public function exportarBloco(string $bloco, string $formato): ?\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (! $this->podeExportar()) {
            abort(403);
        }

        if ($this->relatorio === null) {
            return null;
        }

        AuditLog::registrar('exportou_relatorio', ['bloco' => $bloco, 'formato' => $formato]);

        [$headings, $rows] = $this->linhasDoBloco($bloco);

        $arquivo = "relatorio-{$bloco}-" . now(config('painel.timezone'))->format('Y-m-d-Hi') . ".{$formato}";

        return Excel::download(
            new ArrayExport($rows, $headings),
            $arquivo,
            $formato === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    public function exportarPdf(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->podeExportar() || $this->relatorio === null) {
            abort(403);
        }

        AuditLog::registrar('exportou_relatorio', ['bloco' => 'completo', 'formato' => 'pdf']);

        $medicos = $this->nomeDosMedicos();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', [
            'relatorio' => $this->relatorio,
            'medicos' => $medicos,
        ]);

        $arquivo = 'relatorio-ottoboni-' . now(config('painel.timezone'))->format('Y-m-d-Hi') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $arquivo,
        );
    }

    /**
     * @return array{array<int, string>, array<int, array<string, mixed>>}
     */
    private function linhasDoBloco(string $bloco): array
    {
        $r = $this->relatorio;

        return match ($bloco) {
            'volume' => [
                ['Dia', 'Leads'],
                collect($r['volume']['por_dia'])
                    ->map(fn (int $total, string $dia): array => ['dia' => $dia, 'total' => $total])
                    ->values()
                    ->all(),
            ],
            'origem' => [
                ['Origem', 'Leads', '%'],
                collect($r['origem']['fatias'])
                    ->map(fn (array $f, string $nome): array => ['nome' => $nome, 'total' => $f['total'], 'pct' => $f['pct']])
                    ->values()
                    ->all(),
            ],
            'temperatura' => [
                ['Temperatura', 'Leads', '%'],
                collect($r['temperatura']['fatias'])
                    ->map(fn (array $f, string $nome): array => ['nome' => $nome, 'total' => $f['total'], 'pct' => $f['pct']])
                    ->values()
                    ->all(),
            ],
            'procedimentos' => [
                ['Procedimento', 'Leads', '%'],
                collect($r['procedimentos']['ranking'])
                    ->map(fn (array $f, string $nome): array => ['nome' => $nome, 'total' => $f['total'], 'pct' => $f['pct']])
                    ->values()
                    ->all(),
            ],
            'funil' => [
                ['Funil', 'Etapa', 'Estão nela', 'Chegaram até ela', 'Taxa de passagem %'],
                collect($r['funil']['funis'])
                    ->flatMap(fn (array $funil): array => collect($funil['etapas'])
                        ->map(fn (array $etapa): array => [
                            'funil' => $funil['nome'],
                            'etapa' => $etapa['nome'],
                            'atual' => $etapa['atual'],
                            'chegaram' => $etapa['chegaram'],
                            'taxa' => $etapa['taxa_passagem_pct'],
                        ])
                        ->all())
                    ->all(),
            ],
            'produtividade' => [
                ['Responsável', 'Leads'],
                collect($r['produtividade']['por_responsavel'])
                    ->map(fn (int $total, string $nome): array => ['nome' => $nome, 'total' => $total])
                    ->values()
                    ->all(),
            ],
            default => [[], []],
        };
    }

    /**
     * @return array<int, string>
     */
    private function nomeDosMedicos(): array
    {
        $ids = $this->relatorio['periodo']['pipeline_ids'] ?? [];

        return \App\Models\Doctor::query()
            ->whereIn('kommo_pipeline_id', $ids)
            ->pluck('nome')
            ->all();
    }

    public function podeExportar(): bool
    {
        $user = $this->usuario();

        return $user->isAdmin() || $user->isGestor();
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
