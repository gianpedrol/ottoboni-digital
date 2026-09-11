<?php

namespace App\Support;

/**
 * CPF: validação dos dígitos verificadores, máscara e geração (seed).
 * O valor guardado no banco é só dígitos, cifrado pelo cast do model.
 */
class Cpf
{
    public static function digitos(?string $cpf): string
    {
        return preg_replace('/\D/', '', (string) $cpf) ?? '';
    }

    public static function valido(?string $cpf): bool
    {
        $cpf = self::digitos($cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        return substr($cpf, 9, 2) === self::verificadores(substr($cpf, 0, 9));
    }

    public static function formatar(?string $cpf): ?string
    {
        $cpf = self::digitos($cpf);

        if (strlen($cpf) !== 11) {
            return null;
        }

        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    /**
     * Único formato em que o CPF aparece fora do formulário: ***.***.***-12.
     */
    public static function mascarar(?string $cpf): ?string
    {
        $cpf = self::digitos($cpf);

        if (strlen($cpf) !== 11) {
            return null;
        }

        return '***.***.***-' . substr($cpf, 9, 2);
    }

    /**
     * CPF válido a partir de 9 dígitos-base (fictício, para o seed).
     */
    public static function gerar(int $semente): string
    {
        $base = str_pad((string) (($semente * 7919 + 104729) % 1_000_000_000), 9, '0', STR_PAD_LEFT);

        if (preg_match('/^(\d)\1{8}$/', $base)) {
            $base = '123456789';
        }

        return $base . self::verificadores($base);
    }

    private static function verificadores(string $base): string
    {
        $digitos = $base;

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * (($posicao + 1) - $i);
            }

            $resto = ($soma * 10) % 11;
            $digitos .= (string) ($resto === 10 ? 0 : $resto);
        }

        return substr($digitos, 9, 2);
    }
}
