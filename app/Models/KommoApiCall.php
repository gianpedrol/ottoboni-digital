<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KommoApiCall extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'endpoint',
        'status',
        'duracao_ms',
        'itens',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
