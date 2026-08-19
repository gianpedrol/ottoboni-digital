<?php

namespace App\Jobs;

use App\Models\FollowupRun;
use App\Services\Followup\ExecutorDeFollowup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecutarFollowupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    /**
     * Sem retry automático do framework: o run guarda o próprio estado e
     * o followup:dispatch seguinte retoma o que ficou agendado.
     */
    public int $tries = 1;

    public function __construct(public readonly int $runId) {}

    public function handle(ExecutorDeFollowup $executor): void
    {
        $run = FollowupRun::query()->find($this->runId);

        if ($run !== null) {
            $executor->executar($run);
        }
    }
}
