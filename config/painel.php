<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuso do negócio
    |--------------------------------------------------------------------------
    | Todos os filtros de data do usuário são interpretados neste fuso e
    | convertidos para timestamp UTC antes de ir para a API do Kommo.
    */

    'timezone' => 'America/Sao_Paulo',

    /*
    |--------------------------------------------------------------------------
    | Webhook do n8n (Fase 2 — FOLLOWUP EXECUTOR)
    |--------------------------------------------------------------------------
    */

    'n8n' => [
        'followup_url' => env('N8N_FOLLOWUP_URL'),
        'webhook_secret' => env('N8N_WEBHOOK_SECRET'),
        'timeout' => (int) env('N8N_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Área de treinamento das agentes de IA (Duda e Luna)
    |--------------------------------------------------------------------------
    | envio_url        -> workflow "IA ENVIO APROVADO" no n8n
    | webhook_secret   -> HMAC das duas pontas (painel <-> n8n)
    | fcm_*            -> push no celular para avisar pendência na fila
    */

    'ia' => [
        'envio_url' => env('IA_ENVIO_URL'),
        'webhook_secret' => env('IA_WEBHOOK_SECRET'),
        'timeout' => (int) env('IA_TIMEOUT', 15),
        'fcm_url' => env('FCM_URL'),
        'fcm_server_key' => env('FCM_SERVER_KEY'),

        // Usados só pelo `ia:importar-supabase`, na virada da base para o
        // MySQL. Memória de conversa e echo continuam no Supabase.
        'supabase_url' => env('SUPABASE_URL'),
        'supabase_key' => env('SUPABASE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Janela de horário permitido para envio de follow-up
    |--------------------------------------------------------------------------
    | Padrão 09:00–20:00, segunda a sábado. Ajustável na tela Configurações.
    */

    'janela_envio' => [
        'inicio' => '09:00',
        'fim' => '20:00',
        'dias' => [1, 2, 3, 4, 5, 6], // ISO: 1 = segunda … 6 = sábado
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites de segurança do follow-up
    |--------------------------------------------------------------------------
    */

    'followup' => [
        'max_por_lead_24h' => 1,
        'max_por_lead_total' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção de logs (LGPD / higiene)
    |--------------------------------------------------------------------------
    */

    'retencao_logs_dias' => 90,
];
