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
    | Modo protótipo (Fase 3)
    |--------------------------------------------------------------------------
    | Liga a faixa "Protótipo" no topo do painel. Nesse modo nenhuma tela
    | nova grava no Kommo: criar paciente, agenda e automações só simulam e
    | mostram o que seria enviado.
    */

    'prototipo' => (bool) env('PAINEL_PROTOTIPO', true),

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
