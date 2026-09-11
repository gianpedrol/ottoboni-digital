<?php

namespace App\Filament\Concerns;

use App\Support\Demonstracao;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Páginas com dado fictício mostram o selo no subtítulo.
 */
trait ComSeloDemonstracao
{
    public function getSubheading(): string|Htmlable|null
    {
        return Demonstracao::selo();
    }
}
