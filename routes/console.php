<?php

use Illuminate\Support\Facades\Schedule;

// Retenção de logs (LGPD): kommo_api_calls e webhook_logs limpos após 90 dias.
Schedule::command('painel:limpar-logs')->dailyAt('03:00');

// Motor de follow-up: enfileira os runs vencidos a cada 5 minutos e varre
// as réguas ativas de hora em hora para pegar leads novos nos gatilhos
// (idempotente — a chave única impede agendamento duplicado).
Schedule::command('followup:dispatch')->everyFiveMinutes();
Schedule::command('followup:agendar')->hourly();

// Vigia da fila de aprovação das agentes de IA: avisa pendências e expira o
// que ficou 24h sem revisão. Nunca envia resposta por conta própria.
Schedule::command('ia:vigia-fila')->everyFiveMinutes();
