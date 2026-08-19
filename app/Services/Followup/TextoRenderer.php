<?php

namespace App\Services\Followup;

use App\Services\Kommo\DTO\LeadData;
use Illuminate\Support\Str;

/**
 * Substitui placeholders do texto fixo por dados DO REGISTRO do lead —
 * a mesma trava das agentes: nunca inventar contexto (seção 9.5).
 *
 * Placeholders aceitos: {nome}, {procedimento}, {temperatura}, {instagram}.
 * Placeholder sem valor no lead é removido com a sobra de espaços.
 */
class TextoRenderer
{
    public static function render(string $texto, LeadData $lead): string
    {
        $nome = $lead->geradoPelaAgente()
            ? ($lead->instagramHandle() ?? '')
            : Str::of($lead->name)->before(' ')->toString();

        $valores = [
            '{nome}' => trim($nome),
            '{procedimento}' => trim((string) $lead->procedimento),
            '{temperatura}' => trim((string) $lead->temperatura),
            '{instagram}' => trim((string) $lead->instagramHandle()),
        ];

        $rendered = strtr($texto, $valores);

        // Limpa espaços duplicados deixados por placeholders vazios.
        return Str::of($rendered)->replaceMatches('/ {2,}/', ' ')->trim()->toString();
    }
}
