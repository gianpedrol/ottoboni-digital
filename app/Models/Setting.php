<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $valor
 */
class Setting extends Model
{
    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['chave', 'valor'];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }

    public static function get(string $chave, mixed $default = null): mixed
    {
        $setting = static::query()->find($chave);

        return $setting === null ? $default : $setting->valor;
    }

    public static function set(string $chave, mixed $valor): void
    {
        static::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }
}
