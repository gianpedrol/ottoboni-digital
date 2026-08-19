<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conta Kommo da clínica
    |--------------------------------------------------------------------------
    | Um único Kommo (subdomínio "ottoboni") com dois pipelines, um por médico.
    | O token é de longa duração (Bearer) e NUNCA sai do .env.
    */

    'subdomain' => env('KOMMO_SUBDOMAIN', 'ottoboni'),
    'token' => env('KOMMO_TOKEN'),

    'base_url' => 'https://' . env('KOMMO_SUBDOMAIN', 'ottoboni') . '.kommo.com/api/v4',

    /*
    |--------------------------------------------------------------------------
    | Limites da API
    |--------------------------------------------------------------------------
    | O Kommo permite 7 req/s; trabalhamos com margem. Estourar devolve 429,
    | insistir bloqueia o IP (403). Máximo de 250 entidades por página.
    */

    'rate_per_second' => (int) env('KOMMO_RATE_PER_SECOND', 5),
    'page_limit' => 250,
    'retries' => 3,

    /*
    |--------------------------------------------------------------------------
    | Pipelines (um por médico/agente)
    |--------------------------------------------------------------------------
    */

    'pipelines' => [
        'duda' => (int) env('DUDA_PIPELINE_ID', 6441483),
        'luna' => (int) env('LUNA_PIPELINE_ID', 9219740),
    ],

    /*
    |--------------------------------------------------------------------------
    | Status padrão do Kommo (iguais em qualquer pipeline)
    |--------------------------------------------------------------------------
    */

    'status_ganho' => 142,
    'status_perdido' => 143,

    /*
    |--------------------------------------------------------------------------
    | Campos personalizados preenchidos pelas agentes
    |--------------------------------------------------------------------------
    | Os IDs variam por conta — são resolvidos POR NOME via
    | GET /leads/custom_fields e guardados em cache (ver CustomFieldMap).
    | Aqui ficam apenas os nomes esperados dos campos.
    */

    'custom_fields' => [
        'origem' => env('KOMMO_CF_ORIGEM', 'Origem'),
        'temperatura' => env('KOMMO_CF_TEMPERATURA', 'Temperatura'),
        'procedimento' => env('KOMMO_CF_PROCEDIMENTO', 'Procedimento'),
        'urgencia' => env('KOMMO_CF_URGENCIA', 'Urgência'),
        'score' => env('KOMMO_CF_SCORE', 'Score'),
        'instagram' => env('KOMMO_CF_IG', 'Instagram'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache (essencial — não há espelho local do CRM)
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'list_ttl' => (int) env('LIST_CACHE_TTL', 300),
        'report_ttl' => (int) env('REPORT_CACHE_TTL', 900),
        'fields_ttl' => 86400,
        'pipelines_ttl' => 86400,
        'users_ttl' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limiares para relatório virar job na fila
    |--------------------------------------------------------------------------
    */

    'report_job_threshold_days' => 60,
    'report_job_threshold_leads' => 2000,
];
