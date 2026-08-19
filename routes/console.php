<?php

use Illuminate\Support\Facades\Schedule;

// Retenção de logs (LGPD): kommo_api_calls e webhook_logs limpos após 90 dias.
Schedule::command('painel:limpar-logs')->dailyAt('03:00');
