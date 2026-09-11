<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ModoAutomacao: string implements HasColor, HasDescription, HasLabel
{
    case SoRegistrar = 'so_registrar';
    case Ativo = 'ativo';
    case Desligado = 'desligado';

    public function getLabel(): string
    {
        return match ($this) {
            self::SoRegistrar => 'Só registrar',
            self::Ativo => 'Ativo',
            self::Desligado => 'Desligado',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SoRegistrar => 'Registra o que faria, sem escrever no Kommo.',
            self::Ativo => 'Executa as ações no Kommo (no protótipo, só registra).',
            self::Desligado => 'Não avalia nenhum evento.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SoRegistrar => 'info',
            self::Ativo => 'success',
            self::Desligado => 'gray',
        };
    }
}
