<?php

namespace App\Jobs;

use App\Models\FollowupPlan;
use App\Services\Followup\AgendadorDeFollowups;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AgendarFollowupsDoPlanoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly int $planId) {}

    public function handle(AgendadorDeFollowups $agendador): void
    {
        $plan = FollowupPlan::query()->find($this->planId);

        if ($plan === null) {
            return;
        }

        $novos = $agendador->agendarParaPlano($plan);

        Log::info('Régua varrida', ['plan_id' => $plan->id, 'runs_novos' => $novos]);
    }
}
