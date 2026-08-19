<?php

namespace App\Console\Commands;

use App\Enums\FollowupRunStatus;
use App\Jobs\ExecutarFollowupJob;
use App\Models\FollowupRun;
use App\Services\Followup\JanelaDeEnvio;
use Illuminate\Console\Command;

class FollowupDispatch extends Command
{
    protected $signature = 'followup:dispatch';

    protected $description = 'Enfileira os follow-ups agendados que já venceram e estão dentro da janela de envio';

    public function handle(): int
    {
        if (! JanelaDeEnvio::dentro(now())) {
            $this->info('Fora da janela de envio — nada enfileirado.');

            return self::SUCCESS;
        }

        $devidos = FollowupRun::query()
            ->where('status', FollowupRunStatus::Agendado)
            ->where('agendado_para', '<=', now())
            ->orderBy('agendado_para')
            ->limit(100)
            ->get();

        foreach ($devidos as $run) {
            ExecutarFollowupJob::dispatch($run->id);
        }

        $this->info("{$devidos->count()} follow-up(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
