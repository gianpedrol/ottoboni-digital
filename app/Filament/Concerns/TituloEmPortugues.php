<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * O Filament passa o rótulo por ucwords ("Contas A Pagar"), que é regra do
 * inglês. Em português só a primeira letra é maiúscula ("Contas a pagar").
 */
trait TituloEmPortugues
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
