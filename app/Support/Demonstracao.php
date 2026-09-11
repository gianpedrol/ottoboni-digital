<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Selo das telas com dado fictício (protótipo da Fase 3).
 * Uso: getSubheading() da página, ou ->description() de uma seção.
 */
class Demonstracao
{
    public static function selo(): HtmlString
    {
        return new HtmlString(view('filament.selo-demonstracao')->render());
    }
}
