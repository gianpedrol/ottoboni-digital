<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CategoriaDespesa: string implements HasLabel
{
    case Aluguel = 'aluguel';
    case Folha = 'folha';
    case Materiais = 'materiais';
    case Anestesista = 'anestesista';
    case Hospital = 'hospital';
    case Marketing = 'marketing';
    case Software = 'software';
    case Contador = 'contador';
    case Energia = 'energia';
    case Impostos = 'impostos';
    case Outros = 'outros';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aluguel => 'Aluguel e condomínio',
            self::Folha => 'Folha de pagamento',
            self::Materiais => 'Materiais cirúrgicos',
            self::Anestesista => 'Anestesista',
            self::Hospital => 'Hospital / centro cirúrgico',
            self::Marketing => 'Marketing',
            self::Software => 'Software e sistemas',
            self::Contador => 'Contabilidade',
            self::Energia => 'Energia e utilidades',
            self::Impostos => 'Impostos e taxas',
            self::Outros => 'Outros',
        };
    }
}
