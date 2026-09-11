<?php

namespace App\Support;

/**
 * Telefone brasileiro. Guardado sempre como "+55 41 99999-8888", para a
 * checagem de duplicidade comparar formatos iguais.
 */
class Telefone
{
    /**
     * Aceita "(41) 99999-8888", "41999998888", "+55 41 99999-8888"...
     * Devolve null se não tiver DDD + 8 ou 9 dígitos.
     */
    public static function normalizar(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone) ?? '';

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        if (! in_array(strlen($digitos), [10, 11], true)) {
            return null;
        }

        $ddd = substr($digitos, 0, 2);
        $numero = substr($digitos, 2);
        $corte = strlen($numero) - 4;

        return '+55 ' . $ddd . ' ' . substr($numero, 0, $corte) . '-' . substr($numero, $corte);
    }

    /**
     * Formato da máscara do formulário: "(41) 99999-8888".
     */
    public static function paraMascara(?string $telefone): ?string
    {
        $normalizado = self::normalizar($telefone);

        if ($normalizado === null) {
            return $telefone;
        }

        [, $ddd, $numero] = explode(' ', $normalizado);

        return "({$ddd}) {$numero}";
    }

    /**
     * Formato que o Kommo recebe: "+5541999998888".
     */
    public static function e164(?string $telefone): ?string
    {
        $normalizado = self::normalizar($telefone);

        return $normalizado === null ? null : '+' . preg_replace('/\D/', '', $normalizado);
    }
}
