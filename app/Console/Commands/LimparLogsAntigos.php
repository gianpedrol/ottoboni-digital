<?php

namespace App\Console\Commands;

use App\Models\KommoApiCall;
use App\Models\WebhookLog;
use Illuminate\Console\Command;

class LimparLogsAntigos extends Command
{
    protected $signature = 'painel:limpar-logs';

    protected $description = 'Remove kommo_api_calls e webhook_logs mais antigos que a retenção configurada (LGPD)';

    public function handle(): int
    {
        $limite = now()->subDays((int) config('painel.retencao_logs_dias'));

        $apiCalls = KommoApiCall::query()->where('created_at', '<', $limite)->delete();
        $webhooks = WebhookLog::query()->where('created_at', '<', $limite)->delete();

        $this->info("Removidos: {$apiCalls} chamadas de API e {$webhooks} logs de webhook anteriores a {$limite->toDateString()}.");

        return self::SUCCESS;
    }
}
