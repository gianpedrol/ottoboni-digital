<?php

namespace App\Jobs;

use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Relatórios de período longo rodam na fila: o job pagina a API com calma
 * (respeitando o throttle), agrega, salva o snapshot e avisa quem pediu.
 * Nada de request HTTP esperando 3 minutos.
 */
class GerarRelatorioJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $snapshotId) {}

    public function handle(ReportService $service): void
    {
        $snapshot = ReportSnapshot::query()->findOrFail($this->snapshotId);

        $snapshot->update(['status' => 'gerando']);

        $filtros = $snapshot->filtros;

        try {
            $dados = $service->generate(
                pipelineIds: array_map(intval(...), $filtros['pipeline_ids']),
                fromLocal: CarbonImmutable::parse($filtros['de'], config('painel.timezone')),
                toLocal: CarbonImmutable::parse($filtros['ate'], config('painel.timezone')),
                deep: true,
            );

            $snapshot->update([
                'status' => 'pronto',
                'dados' => $dados,
                'gerado_em' => now(),
            ]);

            $this->notificar($snapshot, sucesso: true);
        } catch (Throwable $e) {
            $snapshot->update([
                'status' => 'erro',
                'erro' => $e->getMessage(),
            ]);

            $this->notificar($snapshot, sucesso: false);

            throw $e;
        }
    }

    private function notificar(ReportSnapshot $snapshot, bool $sucesso): void
    {
        $user = $snapshot->gerado_por ? User::query()->find($snapshot->gerado_por) : null;

        if ($user === null) {
            return;
        }

        $notification = $sucesso
            ? Notification::make()
                ->success()
                ->title('Relatório pronto')
                ->body('O relatório do período solicitado já pode ser aberto na tela de Relatórios.')
            : Notification::make()
                ->danger()
                ->title('Falha ao gerar relatório')
                ->body('Não foi possível gerar o relatório. Tente novamente ou verifique a conexão com o Kommo.');

        $notification->sendToDatabase($user);
    }
}
