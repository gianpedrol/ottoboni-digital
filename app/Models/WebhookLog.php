<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'direcao',
        'payload',
        'assinatura_ok',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'assinatura_ok' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
