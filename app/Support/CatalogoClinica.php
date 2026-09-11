<?php

namespace App\Support;

use App\Models\Doctor;

/**
 * Procedimentos sugeridos por médico (cadastro de paciente e seed).
 * Chave = agente do médico: duda (Dr. Eduardo) e luna (Dra. Vanessa).
 */
class CatalogoClinica
{
    /**
     * @var array<string, array<int, string>>
     */
    public const PROCEDIMENTOS = [
        'duda' => [
            'Rinoplastia',
            'Mamoplastia de aumento',
            'Mastopexia',
            'Abdominoplastia',
            'Lipoaspiração',
            'Lipo HD',
            'Blefaroplastia',
            'Otoplastia',
            'Mini lifting facial',
            'Gluteoplastia',
        ],
        'luna' => [
            'Toxina botulínica',
            'Preenchimento com ácido hialurônico',
            'Bioestimulador de colágeno',
            'Laser CO2 fracionado',
            'Peeling químico',
            'Tratamento de melasma',
            'Tratamento de acne',
            'Ultraformer (HIFU)',
            'Skinbooster',
            'Harmonização facial',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function procedimentos(?Doctor $doctor): array
    {
        if ($doctor === null) {
            return array_merge(...array_values(self::PROCEDIMENTOS));
        }

        return self::PROCEDIMENTOS[$doctor->agente] ?? [];
    }
}
