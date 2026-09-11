<?php

/*
|--------------------------------------------------------------------------
| Mapa de etapas canônicas (Fase 3)
|--------------------------------------------------------------------------
| Cada pipeline do Kommo nomeia as etapas do seu jeito. O painel trabalha
| com as etapas canônicas de App\Enums\EtapaCanonica e este mapa diz quais
| status_id do Kommo correspondem a cada uma. Sempre por ID, nunca por nome.
|
| 142 (ganho = Contratado) e 143 (perdido) valem para qualquer pipeline e
| não precisam aparecer aqui.
|
| IDs lidos da conta real em 10/09/2026. Quando o Kommo for reestruturado
| (docs/escopo-fase-3.md, seção 1.1), este arquivo vira tabela editável.
*/

return [

    'mapa' => [

        // Dr Eduardo
        6441483 => [
            'novo' => [54917471],
            'em_atendimento' => [54917475, 79620384], // EM ATENDIMENTO, INSTAGRAM
            'fup_1' => [84709188],                    // FOLLOW 24 HRS
            'fup_2' => [84709192],                    // FOLLOW UP 5 DIAS
            'fup_3' => [84710072],                    // FOLLOW 1 / Ñ AGEND
            'nao_agendou' => [69609380],
            'aguardando_sinal' => [75912472, 85174916],
            'consulta_agendada' => [54917479],        // AGENDOU CONSULTA/PROCEDIMENTO
            'consulta_realizada' => [56708683],
            'negociacao' => [54917483],
            'cirurgia_agendada' => [69228540],
        ],

        // Dra Vanessa Ottoboni
        9219740 => [
            'novo' => [71591204],
            'em_atendimento' => [71591208, 108897619], // EM ATENDIMENTO, INSTAGRAM
            'fup_1' => [110706659],
            'fup_2' => [110706663],
            'fup_3' => [110706667],
            'nao_agendou' => [71591216],
            'consulta_agendada' => [71591324, 71591328], // presencial e online
            'consulta_realizada' => [73758392],
            'negociacao' => [71591332],                  // CONSULTORIA
            'cirurgia_agendada' => [71591336],           // AGENDOU PROCEDIMENTO
            'acompanhamento' => [90030524, 91443312],
            'venda_futura' => [71591340],
        ],
    ],
];
