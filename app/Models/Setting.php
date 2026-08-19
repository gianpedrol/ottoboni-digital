<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        return static::query()->find($chave)?->valor ?? $default;
    }

    public static function set(string $chave, mixed $valor): void
    {
        static::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }
}
