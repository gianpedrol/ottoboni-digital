<?php

namespace App\Console\Commands;

use App\Enums\FollowupGatilho;
use App\Jobs\AgendarFollowupsDoPlanoJob;
use App\Models\FollowupPlan;
use Illuminate\Console\Command;

class FollowupAgendar extends Command
{
    protected $signature = 'followup:agendar';

    protected $description = 'Varre as réguas ativas e agenda follow-ups para leads novos que casam com os gatilhos';

    public function handle(): int
    {
        $planos = FollowupPlan::query()
            ->where('ativo', true)
            ->where('gatilho', '!=', FollowupGatilho::Manual)
            ->get();

        foreach ($planos as $plan) {
            AgendarFollowupsDoPlanoJob::dispatch($plan->id);
        }

        $this->info("{$planos->count()} régua(s) enviada(s) para varredura.");

        return self::SUCCESS;
    }
}
