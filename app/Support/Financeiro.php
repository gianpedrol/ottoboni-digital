<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

/**
 * Utilidades do módulo Financeiro: acesso, "hoje" no fuso da clínica e R$.
 */
class Financeiro
{
    /**
     * Financeiro é só de admin e gestor; recepção não vê.
     */
    public static function podeAcessar(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }

    /**
     * Enquanto o painel está em modo protótipo, o que se cadastra pelas
     * telas também é demonstração (o seeder limpa na próxima rodada).
     */
    public static function registroDemo(): bool
    {
        return (bool) config('painel.prototipo');
    }

    public static function hoje(): CarbonImmutable
    {
        return CarbonImmutable::now(config('painel.timezone'))->startOfDay();
    }

    public static function brl(float|int|string|null $valor): string
    {
        return (string) Number::currency((float) $valor, in: 'BRL', locale: 'pt_BR');
    }

    /**
     * Divide um total em N parcelas iguais em centavos; a última absorve o
     * arredondamento (1000 / 3 = 333,33 + 333,33 + 333,34).
     *
     * @return array<int, float>
     */
    public static function dividir(float $total, int $parcelas): array
    {
        $parcelas = max(1, $parcelas);
        $centavos = (int) round($total * 100);
        $base = intdiv($centavos, $parcelas);

        $valores = array_fill(0, $parcelas, $base);
        $valores[$parcelas - 1] = $centavos - $base * ($parcelas - 1);

        return array_map(fn (int $c): float => $c / 100, $valores);
    }
}
