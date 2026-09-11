<?php

namespace App\Casts;

use App\Support\Cpf;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * CPF cifrado no banco, sempre guardado só com os dígitos.
 * (Um mutator comum anularia o cast "encrypted"; aqui os dois passos
 * ficam juntos.)
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class CpfCifrado implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) && $value !== '' ? Crypt::decryptString($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $digitos = Cpf::digitos(is_string($value) ? $value : null);

        return $digitos === '' ? null : Crypt::encryptString($digitos);
    }
}
