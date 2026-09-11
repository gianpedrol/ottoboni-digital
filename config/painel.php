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
    | Cron por URL (hospedagem compartilhada)
    |--------------------------------------------------------------------------
    | GET /cron/{token} roda o schedule:run (e esvazia a fila de banco).
    | Sem CRON_TOKEN o token é derivado da APP_KEY (ver SegredosDoPainel);
    | a tela Configuração da IA mostra a URL pronta para colar na hospedagem.
    */

    'cron_token' => env('CRON_TOKEN'),

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
        // Workflow "IA ENVIO APROVADO" no n8n da Pedrol. Troque no .env se o
        // n8n mudar de endereço.
        'envio_url' => env('IA_ENVIO_URL', 'https://n8n.gianfrancopedrol.com.br/webhook/ia-envio-aprovado'),
        // Vazio = derivado da APP_KEY (ver App\Support\SegredosDoPainel).
        'webhook_secret' => env('IA_WEBHOOK_SECRET'),
        'timeout' => (int) env('IA_TIMEOUT', 15),
        'fcm_url' => env('FCM_URL'),
        'fcm_server_key' => env('FCM_SERVER_KEY'),

        // Usados só pelo `ia:importar-supabase`, na virada da base para o
        // MySQL. Memória de conversa e echo continuam no Supabase.
        // Sem SUPABASE_URL/KEY, o comando pede os cards ao n8n (cards_url):
        // webhook "ia-cards" do workflow IA ENVIO APROVADO, com o mesmo HMAC.
        'supabase_url' => env('SUPABASE_URL'),
        'supabase_key' => env('SUPABASE_KEY'),
        'cards_url' => env('IA_CARDS_URL', 'https://n8n.gianfrancopedrol.com.br/webhook/ia-cards'),
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
