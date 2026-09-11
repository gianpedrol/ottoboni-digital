<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A regra dos 3 caminhos combinada com a Dra. Vanessa, mais o "não sei".
 * TesteGenetico e Consulta são os únicos que podem ser liberados por acurácia.
 */
enum IaIntent: string implements HasLabel
{
    case TesteGenetico = 'teste_genetico';
    case Consulta = 'consulta';
    case QueixaPele = 'queixa_pele';
    case Outro = 'outro';
    case NaoSei = 'nao_sei';
    case Indefinido = 'indefinido';

    public function getLabel(): string
    {
        return match ($this) {
            self::TesteGenetico => 'Teste genético',
            self::Consulta => 'Consulta / programas',
            self::QueixaPele => 'Queixa de pele',
            self::Outro => 'Outro assunto (equipe assume)',
            self::NaoSei => 'Não sei responder',
            self::Indefinido => 'Indefinido',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TesteGenetico, self::Consulta => 'success',
            self::QueixaPele => 'info',
            self::NaoSei => 'danger',
            self::Outro, self::Indefinido => 'warning',
        };
    }
}
